<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\TeamDirectory;

use kintai\Core\BundleContract\Bundle;

final class TeamDirectoryBundle extends Bundle
{
    public function getName(): string
    {
        return 'team-directory';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getLabel(): string
    {
        return __('bundle_team_directory');
    }

    public function getDescription(): string
    {
        return __('bundle_team_directory_desc');
    }

    public function register(): void
    {
        $this->loadViewsFrom($this->getPath() . '/Views', 'team-directory');
        $this->loadRoutesFrom($this->getPath() . '/routes.php');
    }
}
