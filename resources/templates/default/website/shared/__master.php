<!DOCTYPE html>
<html lang="{{LANG}}">

<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">

    <title>{{title}}</title>
    <meta name="description" content="{{meta_description}}">
    <meta property="og:type" content="website" />
    <meta property="og:locale" content="{{t:common.og_locale}}" />
    <meta property="og:site_name" content="Entre Amigos Rifa" />
    <meta property="og:title" content="{{meta_title}}" />
    <meta property="og:description" content="{{meta_description}}"/>
    <meta property="og:url" content="{{meta_url}}" />
    <meta property="og:image" content="{{meta_image}}" />
    <meta property="og:image:secure_url" content="{{meta_image}}" />
    {{meta_image_size}}
    <meta property="og:image:alt" content="{{meta_image_alt}}" />
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="{{meta_title}}" />
    <meta name="twitter:description" content="{{meta_description}}" />
    <meta name="twitter:image" content="{{meta_image}}" />

    <!-- Favicons -->
    <link href="/storage/images/system/favicon.ico" rel="icon">
    <link href="/storage/images/system/favicon.ico" rel="apple-touch-icon">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,300i,400,400i,600,600i,700,700i|Jost:300,300i,400,400i,500,500i,600,600i,700,700i|Poppins:300,300i,400,400i,500,500i,600,600i,700,700i" rel="stylesheet">

    <!-- Vendor CSS Files -->
    <link href="/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="/vendor/glightbox/css/glightbox.min.css" rel="stylesheet">
    <link href="/vendor/remixicon/remixicon.css" rel="stylesheet">
    <link href="/vendor/swiper/swiper-bundle.min.css" rel="stylesheet">

    <!-- Template Main CSS File -->
    <link href="/themes/default/website/css/style.css" rel="stylesheet">

    <!-- Global site tag (gtag.js) - Google Analytics -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-RMZ4BVVB2R"></script>
    <script>
        window.dataLayer = window.dataLayer || [];

        function gtag() {
            dataLayer.push(arguments);
        }
        gtag('js', new Date());

        gtag('config', 'G-RMZ4BVVB2R');
    </script>
    {{viteAssets}}
</head>

<body>

    <main id="main">
        {{content}}
    </main><!-- End #main -->

    <!-- Vendor JS Files (GLightbox/Swiper são iniciados pelo raffle.js) -->
    <script src="/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="/vendor/glightbox/js/glightbox.min.js"></script>
    <script src="/vendor/swiper/swiper-bundle.min.js"></script>
</body>

</html>
