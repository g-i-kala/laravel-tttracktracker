<?php

namespace App\Services;

/**
 * Reading a YouTube playlist failed. `userMessage()` gives a short notice for the UI.
 */
class YoutubePlaylistException extends YoutubeSearchException
{
    public function userMessage(): string
    {
        return match (true) {
            $this->isQuotaExceeded() => 'YouTube API quota used up for today. Try again after the reset.',
            $this->reason === 'missingApiKey' => 'Importing a playlist needs a YouTube Data API key.',
            $this->reason === 'invalidPlaylistUrl' => 'This is not a YouTube playlist link.',
            $this->reason === 'playlistNotFound' => 'Playlist not found. Check the link; private playlists cannot be imported.',
            $this->reason === 'playlistItemsNotAccessible' => 'This playlist is private. Make it public or unlisted, then try again.',
            $this->reason === 'connectionFailed' => 'Could not connect to YouTube. Try again.',
            default => 'YouTube could not read this playlist. Try again.',
        };
    }
}
