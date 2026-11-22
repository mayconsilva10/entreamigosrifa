<div class="adm-toggle-field" x-data="{ on: {{on}} }">
  <div class="adm-toggle-field__head">
    <span class="adm-toggle-field__icon" aria-hidden="true"><i class="bi {{icon}}"></i></span>
    <span class="adm-toggle-field__text">
      <span class="adm-label" id="{{id}}-label">{{label}}</span>
      <span class="adm-hint" id="{{id}}-hint">{{hint}}</span>
    </span>
    <button type="button" class="adm-switch" role="switch" :aria-checked="on" aria-labelledby="{{id}}-label" @click="on = !on; on && $nextTick(() => $refs.input.focus())">
      <span class="adm-switch__thumb" aria-hidden="true"></span>
    </button>
  </div>
  <div class="adm-toggle-field__body" x-show="on" x-collapse>
    <label class="visually-hidden" for="{{id}}">{{label}}</label>
    <input class="adm-input" type="{{type}}" name="{{id}}" id="{{id}}" value="{{value}}" placeholder="{{placeholder}}" x-ref="input"
           :disabled="!on" aria-describedby="{{id}}-hint">
  </div>
</div>
