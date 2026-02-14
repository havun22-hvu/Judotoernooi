package main

import (
	"archive/zip"
	"bytes"
	"encoding/binary"
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

// Magic bytes to find the appended bundle inside the exe
// PHP appends: [bundle.zip bytes] [8 bytes: zip offset as uint64] [8 bytes: magic "JTNOODPK"]
var magic = []byte("JTNOODPK")

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

	// Find own executable path
	exePath, err := os.Executable()
	if err != nil {
		fmt.Println("[FOUT] Kan eigen pad niet bepalen:", err)
		waitForExit()
		return
	}

	// Extract embedded bundle from self
	fmt.Println("[1/4] Bestanden uitpakken...")
	bundleData, err := extractEmbeddedBundle(exePath)
	if err != nil {
		fmt.Println("[FOUT] Kan ingebouwde data niet lezen:", err)
		fmt.Println("       Dit bestand is mogelijk beschadigd. Download opnieuw.")
		waitForExit()
		return
	}

	// Read license from bundle
	licenseData, err := readFileFromZipBytes(bundleData, "license.json")
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

	// Extract bundle zip to temp dir
	if err := extractZipBytes(bundleData, extractDir); err != nil {
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

// extractEmbeddedBundle reads the appended bundle.zip from the exe itself.
// Format: [exe bytes] [bundle.zip bytes] [8 bytes: zip start offset (uint64 LE)] [8 bytes: "JTNOODPK"]
func extractEmbeddedBundle(exePath string) ([]byte, error) {
	f, err := os.Open(exePath)
	if err != nil {
		return nil, fmt.Errorf("kan exe niet openen: %w", err)
	}
	defer f.Close()

	// Read last 16 bytes: 8 bytes offset + 8 bytes magic
	fi, err := f.Stat()
	if err != nil {
		return nil, err
	}
	fileSize := fi.Size()

	if fileSize < 16 {
		return nil, fmt.Errorf("bestand te klein")
	}

	trailer := make([]byte, 16)
	if _, err := f.ReadAt(trailer, fileSize-16); err != nil {
		return nil, fmt.Errorf("kan trailer niet lezen: %w", err)
	}

	// Check magic
	if !bytes.Equal(trailer[8:], magic) {
		return nil, fmt.Errorf("geen ingebouwde data gevonden (magic mismatch)")
	}

	// Read zip offset
	zipOffset := binary.LittleEndian.Uint64(trailer[:8])
	zipSize := fileSize - 16 - int64(zipOffset)

	if zipSize <= 0 || int64(zipOffset) >= fileSize {
		return nil, fmt.Errorf("ongeldige offset in trailer")
	}

	// Read the zip data
	bundleData := make([]byte, zipSize)
	if _, err := f.ReadAt(bundleData, int64(zipOffset)); err != nil {
		return nil, fmt.Errorf("kan bundle data niet lezen: %w", err)
	}

	return bundleData, nil
}

// readFileFromZipBytes reads a single file from an in-memory zip
func readFileFromZipBytes(zipData []byte, fileName string) ([]byte, error) {
	reader, err := zip.NewReader(bytes.NewReader(zipData), int64(len(zipData)))
	if err != nil {
		return nil, err
	}

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

	return nil, fmt.Errorf("bestand '%s' niet gevonden in bundle", fileName)
}

// extractZipBytes extracts an in-memory zip to a directory
func extractZipBytes(zipData []byte, destDir string) error {
	// Clean previous extraction
	os.RemoveAll(destDir)

	reader, err := zip.NewReader(bytes.NewReader(zipData), int64(len(zipData)))
	if err != nil {
		return fmt.Errorf("kan zip niet openen: %w", err)
	}

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
