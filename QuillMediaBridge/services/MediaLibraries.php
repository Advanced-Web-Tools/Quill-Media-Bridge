<?php
namespace QuillMediaBridge\services;

use event\interfaces\{IEvent, IEventListener};
use MediaLibrary\collections\Albums;
use Quill\api\events\MediaLibrariesRequestEvent;
use Quill\api\resources\MediaLibraryProvider;

final class MediaLibraries implements IEventListener
{
    private const MODULE = '/awt_packages/QuillMediaBridge/assets/media-library.js';

    public function handle(IEvent $event): array
    {
        if (!$event instanceof MediaLibrariesRequestEvent) return [];

        $registry = $event->mediaLibraryRegistry;
        $registry->add(new MediaLibraryProvider(
            key: 'MediaLibrary:all',
            name: 'Media Library',
            module: self::MODULE,
        ));
        foreach (new Albums() as $album) {
            $registry->add(new MediaLibraryProvider(
                key: 'MediaLibrary:album:' . $album->id,
                name: 'Media Library - ' . $album->name,
                module: self::MODULE . '?album_id=' . $album->id,
            ));
        }
        return [];
    }
}
