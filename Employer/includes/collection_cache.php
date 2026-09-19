<?php
// Shared disk-backed collection cache for Employer pages.
//
// Extracted verbatim from the duplicated copies in kpis.php / reports.php
// (Item 5): one implementation, identical behavior. The disk file is the
// cross-request cache; the static memo avoids decoding the same large JSON
// twice in one request. Filenames are salted per tenant (session uid) so
// two concurrent employers on shared hosting never swap directories.
function get_cached_collection(
  string $collectionName,
  int $ttlSeconds = 600
): array {
  static $memo = [];
  $tenant = (string) ($_SESSION['uid'] ?? 'guest');
  $memoKey = $tenant . '|' . $collectionName . '|' . $ttlSeconds;
  if (isset($memo[$memoKey])) {
    return $memo[$memoKey];
  }

  $cacheFile =
    sys_get_temp_dir() .
    '/performa_' .
    md5($tenant . '|' . $collectionName) .
    '.json';

  if (
    file_exists($cacheFile) &&
    (time() - (int) @filemtime($cacheFile) < $ttlSeconds)
  ) {
    $data = json_decode(
      (string) @file_get_contents($cacheFile),
      true
    );

    if (is_array($data)) {
      $memo[$memoKey] = $data;
      return $data;
    }
  }

  try {
    $data = firestore_list_documents($collectionName);

    @file_put_contents(
      $cacheFile,
      json_encode($data),
      LOCK_EX
    );

    $result = is_array($data) ? $data : [];
    $memo[$memoKey] = $result;
    return $result;
  } catch (Throwable $e) {
    if (file_exists($cacheFile)) {
      $data = json_decode(
        (string) @file_get_contents($cacheFile),
        true
      );

      if (is_array($data)) {
        $memo[$memoKey] = $data;
        return $data;
      }
    }

    error_log(
      'Employer collection load failed (' . $collectionName . '): ' .
      $e->getMessage()
    );

    return [];
  }
}

function clear_collection_cache(
  string $collectionName
): void {
  $tenant = (string) ($_SESSION['uid'] ?? 'guest');
  $cacheFile =
    sys_get_temp_dir() .
    '/performa_' .
    md5($tenant . '|' . $collectionName) .
    '.json';

  if (file_exists($cacheFile)) {
    @unlink($cacheFile);
  }
}
