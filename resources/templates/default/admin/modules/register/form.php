<div class="adm-auth" x-data="authForm" data-tab="{{tab}}">
  {{brand}}

  <main class="adm-auth__main" id="adm-main">
    <div class="adm-auth__top">{{LOCALE_SWITCHER}}</div>
    <div class="adm-auth__panel">
      <h1 class="adm-auth__title">
        <span x-show="tab === 'login'">{{t:admin.auth.login.title}}</span>
        <span x-show="tab === 'register'" x-cloak>{{t:admin.auth.register.title}}</span>
      </h1>
      <p class="adm-hint adm-auth__subtitle">
        <span x-show="tab === 'login'">{{t:admin.auth.login.subtitle}}</span>
        <span x-show="tab === 'register'" x-cloak>{{t:admin.auth.register.subtitle}}</span>
      </p>

      <div class="adm-auth__tabs" role="tablist" aria-label="{{t:admin.auth.tabs}}">
        <button type="button" role="tab" id="adm-tab-login" aria-controls="adm-panel-login" class="adm-auth__tab"
                :class="tab === 'login' && 'is-active'" :aria-selected="(tab === 'login').toString()" :tabindex="tab === 'login' ? 0 : -1"
                @click="select('login')" @keydown.right.prevent="select('register', true)" @keydown.left.prevent="select('register', true)">{{t:admin.auth.login.tab}}</button>
        <button type="button" role="tab" id="adm-tab-register" aria-controls="adm-panel-register" class="adm-auth__tab"
                :class="tab === 'register' && 'is-active'" :aria-selected="(tab === 'register').toString()" :tabindex="tab === 'register' ? 0 : -1"
                @click="select('register')" @keydown.right.prevent="select('login', true)" @keydown.left.prevent="select('login', true)">{{t:admin.auth.register.tab}}</button>
      </div>

      {{status}}

      <div id="adm-panel-login" role="tabpanel" aria-labelledby="adm-tab-login" x-show="tab === 'login'">
        {{formLogin}}
      </div>
      <div id="adm-panel-register" role="tabpanel" aria-labelledby="adm-tab-register" x-show="tab === 'register'" x-cloak>
        {{formRegister}}
      </div>
    </div>
  </main>
</div>
