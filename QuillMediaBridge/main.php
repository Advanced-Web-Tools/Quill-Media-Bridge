<?php

use runtime\api\RuntimeAPI;
use runtime\enums\ERuntimeFlags;
use Quill\api\events\MediaLibrariesRequestEvent;
use QuillMediaBridge\services\MediaLibraries;

final class QuillMediaBridge extends RuntimeAPI
{
    public function environmentSetup(): void
    {
        parent::environmentSetup();
        $this->setRuntimeFlag(ERuntimeFlags::EventDispatcher);
        $this->waitForPackage('Quill');
        $this->waitForPackage('MediaLibrary');
    }

    public function main(): void
    {
        $this->eventDispatcher->addListener(MediaLibrariesRequestEvent::NAME, new MediaLibraries());
    }
}
