<?php

if (empty($_GET['userid'])) {
    $UserID = $Viewer->id();
} else {
    if (!$Viewer->permitted('users_override_paranoia')) {
        print json_encode(['status' => 'failure']);
        die();
    }
    $UserID = (int)$_GET['userid'];
    if (!$UserID) {
        print json_encode(['status' => 'failure']);
        die();
    }
}

$db = Gazelle\DB::DB();
$db->prepared_query("
    SELECT ag.ArtistID, ag.Name
    FROM bookmarks_artists AS ba
    INNER JOIN artists_group AS ag USING (ArtistID)
    WHERE ba.UserID = ?
    ", $UserID
);
$ArtistList = $db->to_array();

$JsonArtists = [];
foreach ($ArtistList as $Artist) {
    [$ArtistID, $Name] = $Artist;
    $JsonArtists[] = [
        'artistId' => (int)$ArtistID,
        'artistName' => $Name
    ];
}

print json_encode([
    'status' => 'success',
    'response' => [
        'artists' => $JsonArtists
    ]
]);
