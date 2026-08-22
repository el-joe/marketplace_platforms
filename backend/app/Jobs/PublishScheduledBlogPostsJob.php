<?php

namespace App\Jobs;

use App\Models\BlogPost;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PublishScheduledBlogPostsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $posts = BlogPost::where('status', 'scheduled')
            ->where('scheduled_for', '<=', now())
            ->get();

        foreach ($posts as $post) {
            $post->update([
                'status'                => 'published',
                'published_at'          => now(),
                'published_by_admin_id' => null,
            ]);
        }

        if ($posts->isNotEmpty()) {
            Log::info('PublishScheduledBlogPostsJob: published ' . $posts->count() . ' scheduled blog post(s).');
        }
    }
}
