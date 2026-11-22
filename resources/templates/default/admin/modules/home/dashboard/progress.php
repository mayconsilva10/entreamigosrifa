<section class="adm-card adm-dash__section" aria-labelledby="adm-progress-title">
  <header class="adm-section-head">
    <h2 class="adm-section-head__title" id="adm-progress-title"><i class="bi bi-bar-chart-steps" aria-hidden="true"></i>{{t:admin.dashboard.progress.title}}</h2>
    <a class="adm-section-head__link" href="{{all_link}}">{{t:admin.dashboard.progress.view_all}}</a>
  </header>
  <ul class="adm-legend" aria-hidden="true">
    <li><span class="adm-legend__dot adm-legend__dot--paid"></span>{{t:admin.dashboard.progress.paid}}</li>
    <li><span class="adm-legend__dot adm-legend__dot--reserved"></span>{{t:admin.dashboard.progress.reserved}}</li>
    <li><span class="adm-legend__dot adm-legend__dot--free"></span>{{t:admin.dashboard.progress.free}}</li>
  </ul>
  {{cards}}
</section>
