package main

import (
	"archive/zip"
	"encoding/json"
	"fmt"
	"io"
	"net"
	"os"
	"os/exec"
	"os/signal"
	"path/filepath"
	"runtime"
	"strings"
	"syscall"
	"time"
)

// License structure matches PHP OfflineExportService output
type License struct {
	ToernooiID   int    `json:"toernooi_id"`
	ToernooiNaam string `json:"toernooi_naam"`
	Organisator  string `json:"organisator"`
	GeneratedAt  string `json:"generated_at"`
	ExpiresAt    string `json:"expires_at"`
	ValidDays    int    `json:"valid_days"`
	Signature    string `json:"signature"`
}

func main() {
	fmt.Println("+==============================================+")
	fmt.Println("|   JudoToernooi Noodpakket - Offline Server   |")
	fmt.Println("+==============================================+")
	fmt.Println()

	// Find bundle.zip next to the executable
	exePath, err := os.Executable()
	if err != nil {
		fmt.Println("[FOUT] Kan eigen pad niet bepalen:", err)
		waitForExit()
		return
	}
	exeDir := filepath.Dir(exePath)
	bundlePath := filepath.Join(exeDir, "bundle.zip")

	if _, err := os.Stat(bundlePath); os.IsNotExist(err) {
		fmt.Println("[FOUT] bundle.zip niet gevonden naast noodpakket.exe")
		fmt.Println("       Verwacht op:", bundlePath)
		fmt.Println("       Pak het volledige zip bestand uit voordat je start.")
		waitForExit()
		return
	}

	// Read license from bundle
	licenseData, err := readFileFromZip(bundlePath, "license.json")
	if err != nil {
		fmt.Println("[FOUT] Kan license.json niet lezen:", err)
		waitForExit()
		return
	}

	var license License
	if err := json.Unmarshal(licenseData, &license); err != nil {
		fmt.Println("[FOUT] Ongeldige license:", err)
		waitForExit()
		return
	}

	// Check expiration
	expiresAt, err := time.Parse(time.RFC3339, license.ExpiresAt)
	if err != nil {
		fmt.Println("[FOUT] Kan vervaldatum niet lezen:", err)
		waitForExit()
		return
	}

	if time.Now().After(expiresAt) {
		fmt.Printf("[VERLOPEN] Dit noodpakket is verlopen op %s\n", expiresAt.Format("02-01-2006 15:04"))
		fmt.Println("Download een nieuw pakket via judotournament.org")
		waitForExit()
		return
	}

	fmt.Printf("Toernooi: %s\n", license.ToernooiNaam)
	fmt.Printf("Organisator: %s\n", license.Organisator)
	fmt.Printf("Geldig tot: %s\n", expiresAt.Format("02-01-2006 15:04"))
	fmt.Println()

	// Determine extraction directory
	extractDir := filepath.Join(os.TempDir(), fmt.Sprintf("noodpakket_%d", license.ToernooiID))

	// Extract bundle
	fmt.Println("[1/4] Bestanden uitpakken...")
	if err := extractZip(bundlePath, extractDir); err != nil {
		fmt.Println("[FOUT] Kan bestanden niet uitpakken:", err)
		waitForExit()
		return
	}

	// Detect local IP
	fmt.Println("[2/4] Netwerk detecteren...")
	localIP := getLocalIP()

	// Find PHP binary
	phpPath := filepath.Join(extractDir, "php", "php.exe")
	if _, err := os.Stat(phpPath); os.IsNotExist(err) {
		fmt.Println("[FOUT] PHP binary niet gevonden:", phpPath)
		waitForExit()
		return
	}

	// Set up environment for Laravel
	laravelDir := filepath.Join(extractDir, "laravel")
	envPath := filepath.Join(laravelDir, ".env")
	dbPath := filepath.Join(extractDir, "database.sqlite")

	// Create .env file for offline mode
	envContent := fmt.Sprintf(`APP_NAME=JudoToernooi
APP_ENV=offline
APP_KEY=%s
APP_DEBUG=false
APP_URL=http://%s:8000

OFFLINE_MODE=true
TOERNOOI_ID=%d

DB_CONNECTION=sqlite
DB_DATABASE=%s

LOG_CHANNEL=single
LOG_LEVEL=warning

SESSION_DRIVER=file
CACHE_STORE=file
`, readAppKey(extractDir), localIP, license.ToernooiID, filepath.ToSlash(dbPath))

	if err := os.WriteFile(envPath, []byte(envContent), 0644); err != nil {
		fmt.Println("[FOUT] Kan .env niet schrijven:", err)
		waitForExit()
		return
	}

	// Start PHP server
	fmt.Println("[3/4] Server starten...")
	cmd := exec.Command(phpPath, "artisan", "serve", "--host=0.0.0.0", "--port=8000")
	cmd.Dir = laravelDir
	cmd.Stdout = os.Stdout
	cmd.Stderr = os.Stderr

	if err := cmd.Start(); err != nil {
		fmt.Println("[FOUT] Kan server niet starten:", err)
		waitForExit()
		return
	}

	// Wait a moment for server to start
	time.Sleep(2 * time.Second)

	// Open browser
	fmt.Println("[4/4] Browser openen...")
	openBrowser("http://localhost:8000")

	fmt.Println()
	fmt.Println("================================================")
	fmt.Printf("  Server actief op: http://%s:8000\n", localIP)
	fmt.Println("  Tablets verbinden via bovenstaand adres")
	fmt.Println()
	fmt.Println("  SLUIT DIT VENSTER OM DE SERVER TE STOPPEN")
	fmt.Println("================================================")

	// Handle graceful shutdown
	sigChan := make(chan os.Signal, 1)
	signal.Notify(sigChan, syscall.SIGINT, syscall.SIGTERM)

	go func() {
		<-sigChan
		fmt.Println("\nServer stoppen...")
		if cmd.Process != nil {
			cmd.Process.Kill()
		}
		cleanup(extractDir)
		os.Exit(0)
	}()

	// Wait for PHP process to exit
	if err := cmd.Wait(); err != nil {
		// Process was killed, that's expected
		if !strings.Contains(err.Error(), "signal: killed") &&
			!strings.Contains(err.Error(), "exit status") {
			fmt.Println("Server gestopt:", err)
		}
	}

	cleanup(extractDir)
}

func readFileFromZip(zipPath, fileName string) ([]byte, error) {
	reader, err := zip.OpenReader(zipPath)
	if err != nil {
		return nil, err
	}
	defer reader.Close()

	for _, f := range reader.File {
		if f.Name == fileName {
			rc, err := f.Open()
			if err != nil {
				return nil, err
			}
			defer rc.Close()
			return io.ReadAll(rc)
		}
	}

	return nil, fmt.Errorf("bestand '%s' niet gevonden in %s", fileName, filepath.Base(zipPath))
}

func extractZip(zipPath, destDir string) error {
	// Clean previous extraction
	os.RemoveAll(destDir)

	reader, err := zip.OpenReader(zipPath)
	if err != nil {
		return fmt.Errorf("kan zip niet openen: %w", err)
	}
	defer reader.Close()

	for _, f := range reader.File {
		path := filepath.Join(destDir, f.Name)

		// Security: prevent zip slip
		cleanDest := filepath.Clean(destDir) + string(os.PathSeparator)
		if !strings.HasPrefix(filepath.Clean(path)+string(os.PathSeparator), cleanDest) &&
			filepath.Clean(path) != filepath.Clean(destDir) {
			return fmt.Errorf("ongeldig pad in zip: %s", f.Name)
		}

		if f.FileInfo().IsDir() {
			os.MkdirAll(path, 0755)
			continue
		}

		os.MkdirAll(filepath.Dir(path), 0755)

		outFile, err := os.Create(path)
		if err != nil {
			return err
		}

		rc, err := f.Open()
		if err != nil {
			outFile.Close()
			return err
		}

		_, err = io.Copy(outFile, rc)
		rc.Close()
		outFile.Close()
		if err != nil {
			return err
		}
	}

	return nil
}

func readAppKey(extractDir string) string {
	data, err := os.ReadFile(filepath.Join(extractDir, "laravel", "app_key.txt"))
	if err != nil {
		return "base64:OFFLINE_FALLBACK_KEY_DO_NOT_USE_IN_PROD"
	}
	return strings.TrimSpace(string(data))
}

func getLocalIP() string {
	addrs, err := net.InterfaceAddrs()
	if err != nil {
		return "localhost"
	}

	for _, addr := range addrs {
		if ipnet, ok := addr.(*net.IPNet); ok && !ipnet.IP.IsLoopback() {
			if ipnet.IP.To4() != nil {
				return ipnet.IP.String()
			}
		}
	}

	return "localhost"
}

func openBrowser(url string) {
	var cmd *exec.Cmd
	switch runtime.GOOS {
	case "windows":
		cmd = exec.Command("cmd", "/c", "start", url)
	case "darwin":
		cmd = exec.Command("open", url)
	default:
		cmd = exec.Command("xdg-open", url)
	}
	cmd.Start()
}

func cleanup(dir string) {
	fmt.Println("Tijdelijke bestanden opruimen...")
	os.RemoveAll(dir)
}

func waitForExit() {
	fmt.Println()
	fmt.Println("Druk op Enter om af te sluiten...")
	fmt.Scanln()
}
