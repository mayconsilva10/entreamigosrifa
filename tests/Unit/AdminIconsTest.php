<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Todo ícone `bi-*` usado no painel precisa existir na versão local do Bootstrap Icons
 * (public/vendor/bootstrap-icons), senão aparece em branco.
 */
class AdminIconsTest extends TestCase
{
    public function test_every_admin_icon_exists_in_local_bootstrap_icons(): void
    {
        $css = File::get(public_path('vendor/bootstrap-icons/bootstrap-icons.css'));
        preg_match_all('/\.(bi-[a-z0-9-]+)::before/', $css, $matches);
        $available = array_flip($matches[1]);

        $sources = [
            resource_path('templates/default/admin'),
            resource_path('js/admin'),
            resource_path('js/admin.js'),
            app_path('Http/Controllers/Admin'),
            app_path('Support/AdminUi.php'),
            app_path('Services'),
        ];

        $missing = [];
        foreach ($sources as $source) {
            $files = is_dir($source) ? File::allFiles($source) : [new \SplFileInfo($source)];
            foreach ($files as $file) {
                preg_match_all('/(?<![\w-])bi-[a-z0-9]+(?:-[a-z0-9]+)*/', File::get($file->getPathname()), $found);
                foreach (array_unique($found[0]) as $icon) {
                    if (! isset($available[$icon])) {
                        $missing[] = $icon.' ('.str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname()).')';
                    }
                }
            }
        }

        $this->assertSame([], $missing, 'Ícones inexistentes no Bootstrap Icons local: '.implode(', ', $missing));
    }
}
