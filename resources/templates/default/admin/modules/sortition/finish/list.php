<li class="adm-winner {{new}}">
  <span class="adm-winner__num adm-num">{{label}}</span>
  <div class="adm-winner__body">
    {{badge}}
    <strong class="adm-winner__name">{{name}}</strong>
    {{meta}}
  </div>
  <div class="adm-winner__actions">
    {{whatsapp}}
    <form method="post" action="{{delete_url}}" @submit.prevent="confirmRemove($el)" data-label="{{label}}">
      <button type="submit" class="adm-icon-btn adm-icon-btn--danger" aria-label="{{delete_label}}" title="{{delete_label}}"><i class="bi bi-trash" aria-hidden="true"></i></button>
    </form>
  </div>
</li>
