<?php
declare(strict_types=1);

$framework = $argv[1] ?? dirname(__DIR__, 3) . '/Advanced-Web-Tools-Framework';
if (!is_dir($framework . '/awt_src/classes')) throw new RuntimeException('AWT framework is unavailable.');
$temporary = sys_get_temp_dir() . '/quill-media-bridge-' . bin2hex(random_bytes(6));
mkdir($temporary, 0700);
define('CONFIG', $temporary);
define('DATA', $temporary . '/');
define('DB_TYPE', 'sqlite');
define('DEBUG', false);
file_put_contents(CONFIG . '/awt_db.php', '<?php');
register_shutdown_function(static function () use ($temporary): void {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($temporary, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    rmdir($temporary);
});
spl_autoload_register(static function (string $class) use ($framework): void {
    $path = str_replace('\\', '/', $class) . '.php';
    $package = explode('\\', $class)[0];
    $file = in_array($package, ['MediaLibrary', 'Quill', 'QuillMediaBridge'], true)
        ? dirname(__DIR__, 2) . '/' . $package . '/' . $path
        : $framework . '/awt_src/classes/' . $path;
    if (is_file($file)) require_once $file;
});
function check(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
$pdo = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$shared = ['DBEngine' => ['PDO' => $pdo]];
$pdo->exec('CREATE TABLE media_library_album (id INTEGER PRIMARY KEY, name TEXT, admin_id INTEGER, cover_image_id INTEGER, creation_date TEXT, update_date TEXT)');
$pdo->exec('CREATE TABLE media_library_item (id INTEGER PRIMARY KEY, storage_id INTEGER, url TEXT, admin_id INTEGER)');
$pdo->exec('CREATE TABLE media_library_album_item (id INTEGER PRIMARY KEY, item_id INTEGER, album_id INTEGER, admin_id INTEGER, creation_date TEXT)');
$pdo->exec('CREATE TABLE awt_storage (id INTEGER PRIMARY KEY, name TEXT, path TEXT, size INTEGER, middleware TEXT)');

require dirname(__DIR__) . '/QuillMediaBridge/main.php';
$runtime = new QuillMediaBridge();
$runtime->eventDispatcher = new event\EventDispatcher();
$runtime->main();
$request = static function () use ($runtime): Quill\api\resources\MediaLibraryRegistry {
    $registry = new Quill\api\resources\MediaLibraryRegistry();
    $runtime->eventDispatcher->dispatch(new Quill\api\events\MediaLibrariesRequestEvent($registry));
    return $registry;
};
check(count($request()->all()) === 1, 'Universal provider must exist with no albums.');
$pdo->exec("INSERT INTO media_library_album (id, name) VALUES (3, 'Summer'), (7, 'Empty')");
(new database\cache\DatabaseCache())->invalidateTable('media_library_album');
$providers = $request()->all();
check(array_column($providers, 'name') === ['Media Library', 'Media Library - Summer', 'Media Library - Empty'], 'Universal provider must precede every album, including empty albums.');
check(array_column($providers, 'key') === ['MediaLibrary:all', 'MediaLibrary:album:3', 'MediaLibrary:album:7'], 'Provider keys must use stable album IDs.');
check(parse_url($providers[1]->module, PHP_URL_QUERY) === 'album_id=3', 'Album module must select the corresponding feed.');
foreach ($providers as $provider) check($provider->mediaTypes === ['image', 'video'], 'Both media types must be supported.');
$pdo->exec("UPDATE media_library_album SET name = 'Renamed' WHERE id = 3");
(new database\cache\DatabaseCache())->invalidateTable('media_library_album');
check($request()->all()[1]->name === 'Media Library - Renamed', 'Album changes must be visible on the next event.');
$pdo->exec("INSERT INTO awt_storage VALUES (1, 'photo.png', '/photo.png', 10, NULL), (2, 'clip.mp4', '/clip.mp4', 20, NULL)");
$pdo->exec("INSERT INTO media_library_item VALUES (1, 1, '/media/photo.png', NULL), (2, 2, '/media/clip.mp4', NULL)");
$pdo->exec('INSERT INTO media_library_album_item (id, item_id, album_id) VALUES (1, 1, 3)');
(new database\cache\DatabaseCache())->invalidateTable('media_library_album_item');
check(count(new MediaLibrary\collections\MediaItems()) === 2, 'MediaItems collection must be available.');
check(count(new MediaLibrary\collections\AlbumItems()) === 1, 'AlbumItems collection must be available.');
$feed = new MediaLibrary\services\MediaFeed();
check(count($feed->items()['items']) === 2, 'Universal feed must include all media.');
check(array_column($feed->items(albumId: 3, kind: 'image')['items'], 'id') === [1], 'Album feed must include its own image membership.');
check($feed->items(albumId: 3, kind: 'video')['items'] === [], 'Album feed must exclude unrelated videos.');
check($feed->items(albumId: 7)['items'] === [], 'Empty albums must return no media.');
$page = $feed->items(limit: 1);
check($page['has_more'] && $page['next_offset'] === 1, 'Existing feed must support pagination.');
$pdo->exec('DELETE FROM media_library_album WHERE id = 7');
(new database\cache\DatabaseCache())->invalidateTable('media_library_album');
check(count($request()->all()) === 2, 'Deleted albums must disappear from the next registry.');
echo "Quill Media Bridge integration checks passed.\n";
