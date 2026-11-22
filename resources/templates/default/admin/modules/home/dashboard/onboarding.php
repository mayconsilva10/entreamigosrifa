<section class="adm-card adm-onboarding" aria-labelledby="adm-onboarding-title">
  <div class="adm-onboarding__head">
    <span class="adm-empty__icon" aria-hidden="true"><i class="bi bi-lightning-charge"></i></span>
    <div>
      <h2 class="adm-onboarding__title" id="adm-onboarding-title">{{t:admin.dashboard.onboarding.title}}</h2>
      <p class="adm-onboarding__text">{{t:admin.dashboard.onboarding.text}}</p>
    </div>
  </div>
  <div class="adm-onboarding__progress">
    <div class="adm-progress" role="progressbar" aria-valuenow="{{done}}" aria-valuemin="0" aria-valuemax="{{total}}" aria-label="{{progress}}">
      <span class="adm-progress__bar" style="width: {{percent}}%"></span>
    </div>
    <span class="adm-onboarding__count">{{progress}}</span>
  </div>
  <ol class="adm-steps">
    {{steps}}
  </ol>
</section>
