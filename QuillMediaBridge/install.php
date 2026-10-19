<?php

use installer\interfaces\package\actions\IPostInstall;

class QuillMediaBridgeInstall implements IPostInstall
{
    public function postInstall(int $packageId, string $packageName): bool
    {
        return true;
    }
}
