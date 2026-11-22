<article class="adm-kpi {{modifier}}">
  <div class="adm-kpi__head">
    <span class="adm-kpi__label">{{label}}</span>
    <span class="adm-kpi__icon" aria-hidden="true"><i class="bi {{icon}}"></i></span>
  </div>
  <p class="adm-kpi__value" x-data x-count-up="{{raw}}" data-format="{{format}}" data-currency="{{currency}}">{{value}}</p>
  <p class="adm-kpi__hint">{{hint}}</p>
  {{action}}
</article>
