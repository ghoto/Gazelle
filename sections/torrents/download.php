<?php

use Gazelle\Enum\LeechType;
use Gazelle\Util\Irc;

$torrent = (new Gazelle\Manager\Torrent)->findById((int)($_REQUEST['id'] ?? 0));
if (is_null($torrent)) {
    json_or_error('could not find torrent', 404);
}
$torrent->setViewer($Viewer);
$torrentId = $torrent->id();

/* uTorrent Remote and various scripts redownload .torrent files periodically.
 * To prevent this retardation from blowing bandwidth etc., let's block it
 * if the .torrent file has been downloaded four times before.
 */
if (preg_match('/^(BTWebClient|Python-urllib|python-requests|uTorrent)/', $_SERVER['HTTP_USER_AGENT'] ?? 'unknown')
    && $Viewer->torrentDownloadCount($torrentId) > 3
) {
    $Msg = 'You have already downloaded this torrent file four times. If you need to download it again, please do so from your browser.';
    json_or_error($Msg, $Msg, true);
}

/* If this is not their torrent, then see if they have downloaded too
 * many files, compared to completely snatched items. If that is too
 * high, and they have already downloaded too many files recently, then
 * stop them. Exception: always allowed if they are using FL tokens.
 */
$db = Gazelle\DB::DB();
$userId = $Viewer->id();
if (!($_REQUEST['usetoken'] ?? 0) && $torrent->uploaderId() != $userId) {
    $PRL = new \Gazelle\User\PermissionRateLimit($Viewer);
    if (!$PRL->safeFactor() && !$PRL->safeOvershoot()) {
        $db->prepared_query('
            INSERT INTO ratelimit_torrent
                   (user_id, torrent_id)
            VALUES (?,       ?)
            ON DUPLICATE KEY UPDATE logged = now()
            ', $userId, $torrentId
        );
        if ($Cache->get_value('user_429_flood_' . $userId)) {
            $Cache->increment('user_429_flood_' . $userId);
        } else {
            Irc::sendMessage(
                STATUS_CHAN,
                $Viewer->publicLocation()
                . " (" . $Viewer->username() . ")"
                . " (" . geoip($_SERVER['REMOTE_ADDR']) . ")"
                . " accessing "
                . SITE_URL . $_SERVER['REQUEST_URI']
                . (!empty($_SERVER['HTTP_REFERER']) ? " from " . $_SERVER['HTTP_REFERER'] : '')
                . ' hit download rate limit'
            );
            $Cache->cache_value('user_429_flood_' . $userId, 1, 3600);
        }
        json_or_error('rate limiting hit on downloading', 429);
    }
}

/* If they are trying use a token on this, we need to make sure they
 * have enough. If so, deduct the number required, note it in the freeleech
 * table and update their cache key.
 */

if (isset($_REQUEST['usetoken']) && $torrent->leechType() == LeechType::Normal) {
    if (!$Viewer->canSpendFLToken($torrent)) {
        json_or_error('You cannot use tokens here (leeching suspended or already freeleech).');
    }

    // First make sure this isn't already FL, and if it is, do nothing
    if (!$torrent->hasToken($userId)) {
        $tokenCount = $torrent->tokenCount();
        if (!STACKABLE_FREELEECH_TOKENS && $tokenCount > 1) {
            json_or_error('This torrent is too large. Please use the regular DL link.');
        }
        $db->begin_transaction();
        $db->prepared_query('
            UPDATE user_flt SET
                tokens = tokens - ?
            WHERE tokens >= ? AND user_id = ?
            ', $tokenCount, $tokenCount, $userId
        );
        if ($db->affected_rows() == 0) {
            $db->rollback();
            json_or_error('You do not have enough freeleech tokens. Please use the regular DL link.');
        }

        // Let the tracker know about this
        if (!(new Gazelle\Tracker)->update_tracker('add_token', [
            'info_hash' => $torrent->infohashEncoded(), 'userid' => $userId
        ])) {
            $db->rollback();
            json_or_error('Sorry! An error occurred while trying to register your token. Most often, this is due to the tracker being down or under heavy load. Please try again later.');
        }
        $db->prepared_query("
            INSERT INTO users_freeleeches (UserID, TorrentID, Uses, Time)
            VALUES (?, ?, ?, now())
            ON DUPLICATE KEY UPDATE
                Time = VALUES(Time),
                Expired = FALSE,
                Uses = Uses + ?
            ", $userId, $torrentId, $tokenCount, $tokenCount
        );
        $db->commit();
        $Cache->delete_multi(["u_$userId", "users_tokens_$userId"]);
    }
}

$Viewer->registerDownload($torrentId);

if ($torrent->group()->categoryId() == 1 && $torrent->group()->image() != '' && $torrent->uploaderId() != $userId) {
    $Viewer->flushRecentSnatch();
}

header('Content-Type: ' . ($Viewer->downloadAsText() ? 'text/plain' : 'application/x-bittorrent') . '; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $torrent->torrentFilename($Viewer->downloadAsText(), MAX_PATH_LEN) . '"');
echo $torrent->torrentBody($Viewer->announceUrl());
