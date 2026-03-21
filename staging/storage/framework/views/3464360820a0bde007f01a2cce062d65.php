<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'title' => null,
    'description' => null,
    'canonical' => null,
    'ogImage' => null,
    'type' => 'website',
    'noindex' => false,
]));

foreach ($attributes->all() as $__key => $__value) {
    if (in_array($__key, $__propNames)) {
        $$__key = $$__key ?? $__value;
    } else {
        $__newAttributes[$__key] = $__value;
    }
}

$attributes = new \Illuminate\View\ComponentAttributeBag($__newAttributes);

unset($__propNames);
unset($__newAttributes);

foreach (array_filter(([
    'title' => null,
    'description' => null,
    'canonical' => null,
    'ogImage' => null,
    'type' => 'website',
    'noindex' => false,
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars); ?>

<?php
    $appUrl = rtrim(config('app.url'), '/');
    $currentPath = request()->getPathInfo();
    $canonicalUrl = $canonical ?? $appUrl . $currentPath;
    $ogImageUrl = $ogImage ? (str_starts_with($ogImage, 'http') ? $ogImage : $appUrl . $ogImage) : $appUrl . '/icon-512x512.png';
    $currentLocale = app()->getLocale();
    $alternateLocale = $currentLocale === 'nl' ? 'en' : 'nl';

    // Build hreflang URLs with locale parameter
    $currentUrl = url()->current();
    $hreflangSeparator = str_contains($currentUrl, '?') ? '&' : '?';
?>

<?php if($description): ?>
<meta name="description" content="<?php echo e($description); ?>">
<?php endif; ?>

<?php if($noindex): ?>
<meta name="robots" content="noindex, nofollow">
<?php endif; ?>

<link rel="canonical" href="<?php echo e($canonicalUrl); ?>">


<link rel="alternate" hreflang="nl" href="<?php echo e($currentUrl . $hreflangSeparator . 'locale=nl'); ?>">
<link rel="alternate" hreflang="en" href="<?php echo e($currentUrl . $hreflangSeparator . 'locale=en'); ?>">
<link rel="alternate" hreflang="x-default" href="<?php echo e($currentUrl); ?>">


<meta property="og:type" content="<?php echo e($type); ?>">
<?php if($title): ?>
<meta property="og:title" content="<?php echo e($title); ?>">
<?php endif; ?>
<?php if($description): ?>
<meta property="og:description" content="<?php echo e($description); ?>">
<?php endif; ?>
<meta property="og:url" content="<?php echo e($canonicalUrl); ?>">
<meta property="og:image" content="<?php echo e($ogImageUrl); ?>">
<meta property="og:locale" content="<?php echo e($currentLocale === 'nl' ? 'nl_NL' : 'en_GB'); ?>">
<meta property="og:locale:alternate" content="<?php echo e($currentLocale === 'nl' ? 'en_GB' : 'nl_NL'); ?>">
<meta property="og:site_name" content="<?php echo e(__('JudoToernooi')); ?>">


<meta name="twitter:card" content="summary_large_image">
<?php if($title): ?>
<meta name="twitter:title" content="<?php echo e($title); ?>">
<?php endif; ?>
<?php if($description): ?>
<meta name="twitter:description" content="<?php echo e($description); ?>">
<?php endif; ?>
<meta name="twitter:image" content="<?php echo e($ogImageUrl); ?>">


<?php if(app()->environment('production')): ?>
<script async src="https://www.googletagmanager.com/gtag/js?id=G-42KGYDWS5J"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', 'G-42KGYDWS5J');
</script>
<?php endif; ?>
<?php /**PATH /var/www/judotoernooi/staging/resources/views/components/seo.blade.php ENDPATH**/ ?>