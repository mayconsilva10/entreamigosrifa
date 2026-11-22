<a class="adm-skip" href="#adm-main">{{t:admin.shell.skip_link}}</a>

<div class="adm-shell" x-data>
  {{menu}}

  <div class="adm-shell__body">
    {{menu_top}}

    <main id="adm-main" class="adm-main" tabindex="-1">
      <div class="adm-flashes">{{flash}}</div>
      <div class="container-fluid adm-container">
        {{content}}
      </div>
    </main>

    <footer class="adm-footer">
      <span>&copy; {{year}} {{t:admin.shell.footer}}</span>
    </footer>
  </div>

  {{bottom_nav}}
</div>

{{toasts}}
{{confirm}}
