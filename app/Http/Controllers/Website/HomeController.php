<?php

namespace App\Http\Controllers\Website;

use App\Support\LegacyView;

class HomeController extends SiteController
{
    public function index(): string
    {
        return $this->renderMaster('Home', LegacyView::render('website/site/home', ['header' => $this->header(innerPage: false)]));
    }

    public function terms(): string
    {
        return $this->renderMaster('Termos de Servico', LegacyView::render('website/site/terms-service', ['header' => $this->header()]));
    }

    public function privacy(): string
    {
        return $this->renderMaster('Politica de Privacidade', LegacyView::render('website/site/policie-privacity', ['header' => $this->header()]));
    }

    public function listAll(): string
    {
        return $this->renderMaster('Rifas', LegacyView::render('website/site/categories/index', ['header' => $this->header()]));
    }
}
