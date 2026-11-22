<!DOCTYPE html>
<html lang="{{LANG}}">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="{{t:admin.meta_description}}">
  <meta name="robots" content="noindex, nofollow">
  <meta name="color-scheme" content="light dark">

  <title>{{title}}</title>

  <link href="/storage/images/system/favicon.ico" rel="icon">
  <link href="/storage/images/system/favicon.ico" rel="apple-touch-icon">

  <!-- Tema e menu salvos aplicados antes da pintura (evita piscar) -->
  <script>
    try {
      var admTheme = localStorage.getItem('adm-theme');
      if (admTheme === 'dark' || admTheme === 'light') document.documentElement.setAttribute('data-theme', admTheme);
      if (localStorage.getItem('adm-sidebar') === 'collapsed') document.documentElement.setAttribute('data-sidebar', 'collapsed');
    } catch (e) {}
  </script>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

  <link href="/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">

  <script>window.__ADMIN__ = {{adminData}};</script>
  {{viteAssets}}
</head>

<body class="adm-body">
  {{content}}

  <script src="/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script>
    window.dataLayer = window.dataLayer || [];

    function gtag() {
      dataLayer.push(arguments);
    }
    gtag('js', new Date());
    gtag('config', 'G-RMZ4BVVB2R');
  </script>
</body>

</html>
