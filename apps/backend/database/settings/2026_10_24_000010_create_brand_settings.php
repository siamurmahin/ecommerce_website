<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /** Defaults are the approved design tokens (CLAUDE.md → Design tokens). */
    public function up(): void
    {
        $this->migrator->add('brand.name', 'Looks Jhorna');
        $this->migrator->add('brand.tagline', null);
        $this->migrator->add('brand.tagline_en', null);
        $this->migrator->add('brand.logo', null);
        $this->migrator->add('brand.favicon', null);
        $this->migrator->add('brand.color_primary', '#0F4C3A');
        $this->migrator->add('brand.color_accent', '#B8975A');
        $this->migrator->add('brand.color_background', '#F8F8F6');
        $this->migrator->add('brand.color_surface', '#FFFFFF');
        $this->migrator->add('brand.color_text', '#171717');
        $this->migrator->add('brand.color_muted', '#6B6B68');
        $this->migrator->add('brand.color_border', '#E6E6E2');
        $this->migrator->add('brand.radius', 14);
        $this->migrator->add('brand.font_heading', 'fraunces');
        $this->migrator->add('brand.font_body', 'manrope');
        $this->migrator->add('brand.font_heading_bn', 'noto-serif-bengali');
        $this->migrator->add('brand.font_body_bn', 'noto-sans-bengali');
    }
};
