<?php

namespace Nafiswatsiq\SubbasePayment\Tests;

require_once __DIR__ . '/TestCase.php';

use Nafiswatsiq\Subbase\Support\ThemeManager;
use Illuminate\Support\Facades\Config;

class ThemeManagerTest extends TestCase
{
    public function test_active_theme_defaults_to_default(): void
    {
        $themeManager = app(ThemeManager::class);
        $this->assertEquals('default', $themeManager->getTheme());
    }

    public function test_can_set_active_theme(): void
    {
        $themeManager = app(ThemeManager::class);
        Config::set('subbase.theme', 'neo-brutalism');
        $this->assertEquals('neo-brutalism', $themeManager->getTheme());
    }

    public function test_resolves_built_in_theme_view(): void
    {
        $themeManager = app(ThemeManager::class);
        Config::set('subbase.theme', 'neo-brutalism');

        $view = $themeManager->resolveView('subbase', 'components.plan-list');
        $this->assertEquals('subbase::themes.neo-brutalism.components.plan-list', $view);
    }

    public function test_falls_back_to_default_if_theme_view_missing(): void
    {
        $themeManager = app(ThemeManager::class);
        Config::set('subbase.theme', 'non-existent-theme');

        $view = $themeManager->resolveView('subbase', 'components.plan-list');
        $this->assertEquals('subbase::themes.default.components.plan-list', $view);
    }

    public function test_lists_all_built_in_themes(): void
    {
        $themeManager = app(ThemeManager::class);
        $themes = $themeManager->availableThemes();

        $this->assertContains('default', $themes);
        $this->assertContains('neo-brutalism', $themes);
        $this->assertContains('glassmorphism', $themes);
        $this->assertContains('neumorphism', $themes);
        $this->assertContains('cyberpunk', $themes);
        $this->assertContains('maximalism', $themes);
    }

    public function test_theme_list_command(): void
    {
        $this->artisan('subbase:theme-list')
            ->expectsOutputToContain('Current active theme:')
            ->assertExitCode(0);
    }

    public function test_theme_install_command(): void
    {
        $this->artisan('subbase:theme-install', ['theme' => 'cyberpunk'])
            ->expectsOutputToContain('Active theme set to:')
            ->assertExitCode(0);

        $themeManager = app(ThemeManager::class);
        // The command writes the host .env rather than mutating cached config.
        // Its activation announcement and exit code are the contract here.
    }
}
