<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\AdSupportArticle;
use App\Models\AdSupportCollection;
use Illuminate\Database\Seeder;

class AdSupportSeeder extends Seeder
{
    public function run(): void
    {
        $author = Admin::first();

        $collections = [
            ['slug' => 'getting-started', 'name' => 'Getting Started', 'description' => 'Advertising on noon can significantly boost your brand’s visibility and help you connect with high-intent customers actively searching for products like yours.', 'icon' => 'https://intercom.help/noon-adsupport/assets/svg/icon:other-rocket-launch', 'sort_order' => 1],
            ['slug' => 'quick-guides', 'name' => 'Quick Guides', 'description' => 'Find comprehensive guides that will walk you through the process of creating and launching ad campaigns.', 'icon' => 'https://intercom.help/noon-adsupport/assets/svg/icon:content-book-open', 'sort_order' => 2],
            ['slug' => 'release-notes', 'name' => 'Release Notes', 'description' => 'Stay informed about the latest noon ads updates.', 'icon' => 'https://intercom.help/noon-adsupport/assets/svg/icon:sft-megaphone', 'sort_order' => 3],
            ['slug' => 'manage-campaigns', 'name' => 'Manage Campaigns', 'description' => 'Deep dive into campaign creation steps', 'icon' => 'https://intercom.help/noon-adsupport/assets/svg/icon:sft-squares-plus', 'sort_order' => 4],
            ['slug' => 'optimize-campaigns', 'name' => 'Optimize Campaigns', 'description' => "Improve your campaigns' performance by adjusting the necessary parameters to achieve your advertising goals.", 'icon' => 'https://intercom.help/noon-adsupport/assets/svg/icon:sft-adjustments-horizontal', 'sort_order' => 5],
            ['slug' => 'billing-payment', 'name' => 'Billing & Payment', 'description' => 'Effectively manage invoices and payments to ensure a smooth advertising experience.', 'icon' => 'https://intercom.help/noon-adsupport/assets/svg/icon:bizz-fin-currency-dollar', 'sort_order' => 6],
            ['slug' => 'account-management', 'name' => 'Account Management', 'description' => 'Easily manage your account settings to customize your advertising experience', 'icon' => 'https://intercom.help/noon-adsupport/assets/svg/icon:people-chat-gets-user-circle', 'sort_order' => 7],
            ['slug' => 'advertising-resources', 'name' => 'Advertising Resources', 'description' => 'Prepare for retail events and get inspired by advertising success stories.', 'icon' => 'https://intercom.help/noon-adsupport/assets/svg/icon:ff-folder-open', 'sort_order' => 8],
            ['slug' => 'faqs', 'name' => 'FAQs', 'description' => '', 'icon' => 'https://intercom.help/noon-adsupport/assets/svg/icon:arr-sym-question-mark-circle', 'sort_order' => 9],
        ];

        $created = [];
        foreach ($collections as $data) {
            $created[$data['slug']] = AdSupportCollection::firstOrCreate(
                ['slug' => $data['slug']],
                $data + ['is_active' => true]
            );
        }

        $overview = AdSupportCollection::firstOrCreate(
            ['slug' => 'overview'],
            [
                'parent_id' => $created['getting-started']->id,
                'name' => 'Overview',
                'description' => null,
                'icon' => null,
                'sort_order' => 1,
                'is_active' => true,
            ]
        );

        // ── Full real article ───────────────────────────────────────────────
        AdSupportArticle::firstOrCreate(
            ['slug' => 'what-are-self-serve-ad-campaigns'],
            [
                'ad_support_collection_id' => $overview->id,
                'author_admin_id' => $author?->id,
                'title' => 'What Are Self-Serve Ad Campaigns',
                'excerpt' => 'Self-serve ads allow sellers and brands to create and manage ad campaigns, offering flexibility, control, and cost-effectiveness.',
                'body' => $this->selfServeAdCampaignsBody(),
                'status' => 'published',
                'published_at' => now()->subMonths(6),
                'is_featured' => false,
                'views_count' => 412,
            ]
        );

        // ── Sibling articles in Getting Started > Overview ──────────────────
        $this->stub($overview->id, $author?->id, 'how-to-navigate-the-campaigns-page', 'How to Navigate the Campaigns Page');
        $this->stub($overview->id, $author?->id, 'how-to-access-ad-manager', 'How to Access Ad Manager');

        // ── Cross-linked articles referenced from the real article ─────────
        $this->stub($created['quick-guides']->id, $author?->id, 'how-to-create-a-product-ads-campaign', 'How to Create a Product Ads Campaign');
        $this->stub($created['quick-guides']->id, $author?->id, 'how-to-scale-your-ads-with-noon-ads', 'How to Scale Your Ads with noon ads');
        $this->stub($created['quick-guides']->id, $author?->id, 'how-to-create-a-brand-ads-campaign', 'How to Create a Brand Ads Campaign');
        $this->stub($created['quick-guides']->id, $author?->id, 'how-to-create-a-display-ads-campaign', 'How to Create a Display Ads Campaign');
        $this->stub($created['optimize-campaigns']->id, $author?->id, 'what-is-campaign-bidding', 'What Is Campaign Bidding');
        $this->stub($created['optimize-campaigns']->id, $author?->id, 'campaign-targeting', 'Campaign Targeting');
        $this->stub($created['optimize-campaigns']->id, $author?->id, 'selecting-skus-to-advertise', 'Selecting SKUs to Advertise');
        $this->stub($created['optimize-campaigns']->id, $author?->id, 'what-is-the-campaign-overview-report', 'What is the Campaign Overview Report');

        // ── Home page "New to ads?" featured callout ────────────────────────
        AdSupportArticle::firstOrCreate(
            ['slug' => 'what-is-the-seller-accelerator-program'],
            [
                'ad_support_collection_id' => $created['getting-started']->id,
                'author_admin_id' => $author?->id,
                'title' => 'What Is the Seller Accelerator Program',
                'excerpt' => 'New sellers get 500 ad credits and a chance to earn up to $1,000 in extra credits during their first months advertising on noon.',
                'body' => '<div><p>The Seller Accelerator Program gives new sellers a head start on advertising: 500 ad credits are applied automatically to your account, and top-performing new advertisers can earn up to an additional $1,000 in credits during their onboarding period.</p><p>To make the most of it, launch a Product Ads campaign on your best-selling SKUs within your first two weeks, and keep an eye on the Campaign Overview Report to see which keywords are converting.</p></div>',
                'status' => 'published',
                'published_at' => now()->subMonths(4),
                'is_featured' => true,
                'views_count' => 1284,
            ]
        );

        // ── Related articles curation for the real article ──────────────────
        $realArticle = AdSupportArticle::where('slug', 'what-are-self-serve-ad-campaigns')->first();
        $relatedSlugs = [
            'how-to-create-a-product-ads-campaign',
            'how-to-scale-your-ads-with-noon-ads',
            'how-to-create-a-brand-ads-campaign',
            'what-is-campaign-bidding',
            'what-is-the-campaign-overview-report',
        ];
        $realArticle->update([
            'related_article_ids' => AdSupportArticle::whereIn('slug', $relatedSlugs)->pluck('id')->all(),
        ]);
    }

    private function stub(string $collectionId, ?string $authorId, string $slug, string $title): void
    {
        AdSupportArticle::firstOrCreate(
            ['slug' => $slug],
            [
                'ad_support_collection_id' => $collectionId,
                'author_admin_id' => $authorId,
                'title' => $title,
                'excerpt' => null,
                'body' => '<div><p>This article is coming soon. In the meantime, explore the related articles below or browse the collections on the Knowledge Hub home page.</p></div>',
                'status' => 'published',
                'published_at' => now()->subMonths(3),
                'is_featured' => false,
                'views_count' => random_int(5, 120),
            ]
        );
    }

    private function selfServeAdCampaignsBody(): string
    {
        return <<<'HTML'
<div><p>Self-serve ads allow sellers and brands to create and manage ad campaigns, offering flexibility, control, and cost-effectiveness. Whether you are a small business or a growing enterprise, noon&rsquo;s self-serve ad platform provides tools to promote your products, increase visibility, and drive sales.</p></div>

<h2 id="h_64477c7a59">Key Benefits</h2>
<ol>
<li><b>Flexibility:</b> Start, pause, or adjust your campaigns at any time</li>
<li><b>Budget Control:</b> Set daily budgets and <a href="/ae/adsupport/articles/what-is-campaign-bidding">bids</a> that match your advertising budget</li>
<li><b>Performance Tracking:</b> Monitor campaigns' performance and optimize them based on data</li>
<li><b>Targeted Advertising:</b> Reach customers searching for products or browsing relevant categories by using <a href="/ae/adsupport/articles/campaign-targeting">auto or manual targeting across categories and keywords</a></li>
</ol>

<h2 id="h_78c3346bf3">How Do They Work</h2>
<p>Self-serve ads operate on an open-auction model, where advertisers compete for targets/placements by setting bids. The ad with the highest ad rank, determined by the bid amount and the relevance score, wins the auction and appears.</p>
<ul>
<li><b><a href="/ae/adsupport/articles/what-is-campaign-bidding">Bid Amount</a>:</b> The amount you're willing to pay per click or thousand impressions, depending on the pricing model</li>
<li><b>Relevance Score</b>: A rating that measures how well your chosen targets (keywords and categories), along with the title and content of your advertised products' detail page, match user intent and influence the likelihood that customers will engage with your advertised product listings and purchase your <a href="/ae/adsupport/articles/selecting-skus-to-advertise">advertised products</a></li>
</ul>

<hr>

<p><b>💡Tip</b></p>
<ul>
<li>A well-optimized product detail page can improve your ad performance</li>
<li>Ensure that your product title is relevant to the keywords that you are using</li>
<li>Update your product description</li>
</ul>

<hr>

<p>The table below explains the Ad ranking process:</p>
<table>
<tbody>
<tr><td style="background-color:#e8e8e880"><b>Ad (Product x Target)</b></td><td style="background-color:#e8e8e880"><b>Bid</b></td><td style="background-color:#e8e8e880"><b>Relevance Score</b></td><td style="background-color:#e8e8e880"><b>Total Score (Bid x Relevance)</b></td><td style="background-color:#e8e8e880"><b>Ad Ranking</b></td></tr>
<tr><td style="background-color:#feedaf80">Ad 1</td><td style="background-color:#feedaf80">0.8</td><td style="background-color:#feedaf80">0.9</td><td style="background-color:#feedaf80">0.72</td><td style="background-color:#feedaf80">1</td></tr>
<tr><td>Ad 2</td><td>1.0</td><td>0.6</td><td>0.6</td><td>2</td></tr>
<tr><td>Ad 3</td><td>0.5</td><td>0.5</td><td>0.25</td><td>3</td></tr>
</tbody>
</table>

<h1 id="h_eb7fd47359"><b>Comparison Between Ad Types</b></h1>
<p>noon ads offers three self-serve ad solutions &mdash; Product Ads, Brand Ads, and Display Ads &mdash; each having unique objectives and catering to different advertising needs.</p>

<h2 id="h_e7db1e84b4"><b>Product Ads</b></h2>
<p><a href="/ae/adsupport/articles/how-to-create-a-product-ads-campaign">Product Ads</a> are designed to drive sales and increase individual product visibility by appearing in key placements (see image below).</p>
<div style="background-color:#e3e7fa80;border:1px solid #334bfa33;padding:16px;border-radius:8px"><p><b>Example</b>: A seller promoting an air fryer can have their ad appear at the top of relevant search results when a customer searches for "air fryer".</p></div>
<p>This placement targets high-intent shoppers, increasing the likelihood of a purchase.</p>
<p><img src="https://downloads.intercomcdn.com/i/o/yba8j1xj/1443729459/0536dfc1be3ce4d82369cd54635a/image.png?expires=1783600200&signature=4469ff38c94198cb363e3d84c752e5a9be212971a9336ea1933af13d3cf90574&req=dSQjFc58lIVaUPMW1HO4zcJDK6Ow1tXJakJakIOvhoZVh%2FPir0mziF61RLcp%0AAso4li6pajaZ3lniAd8%3D%0A" alt="" width="996" height="472" loading="lazy"></p>

<h2 id="h_0ffccff13d"><b>Brand Ads</b></h2>
<p><a href="/ae/adsupport/articles/how-to-create-a-brand-ads-campaign">Brand Ads</a> focus on building brand awareness and enhancing brand discovery by showcasing a brand&rsquo;s logo, custom headline, and multiple products in key placements (see image below). Available in banner and video formats, these ads help brands capture customers' attention and drive engagement.</p>
<div style="background-color:#e3e7fa80;border:1px solid #334bfa33;padding:16px;border-radius:8px"><p><b>Example:</b> A sportswear brand can use Brand Ads to display shoes, apparel, and accessories, encouraging shoppers to explore its product range.</p></div>
<p><img src="https://downloads.intercomcdn.com/i/o/yba8j1xj/1503326406/b12b587592c7c8d64f81c064bcc5/image+%2866%29.png?expires=1783600200&signature=58a8343b492509d8fee31c29a89387ae20930d8b250d8819edb265ef44c0b92c&req=dSUnFcp8m4VfX%2FMW1HO4zdtxNBzQbA5P9SwSUfzSFMr5W%2BKRTBvPHgGr8U4d%0AvC7gj%2BF4l6o1Df%2BOs6A%3D%0A" alt="" width="1012" height="506" loading="lazy"></p>

<h2 id="h_cf8bd96569"><b>Display Ads</b></h2>
<p><a href="/ae/adsupport/articles/how-to-create-a-display-ads-campaign">Display Ads</a> are static graphic banners ideal for boosting brand awareness and promoting campaigns such as seasonal sales or new product launches. These ads are placed in high-visibility locations (see image below).</p>
<div style="background-color:#e3e7fa80;border:1px solid #334bfa33;padding:16px;border-radius:8px"><p><b>Example: </b>A skincare brand running a clearance sale can use Display Ads on the homepage to capture attention and redirect traffic to the sale page.</p></div>
<p><img src="https://downloads.intercomcdn.com/i/o/yba8j1xj/1401228719/5ff955af1b98b0e81c3a91eb23b0/Screen+Shot+2025-02-28+at+4_45_07+PM.png?expires=1783600200&signature=2ef14dbc514275f9f819750669f0b672a4556de2200632e8b05d852c544465a2&req=dSQnF8t8lYZeUPMW1HO4zVK54JyCE4dMnr09D9leO40%2FCgdkzBV55veGq7Zm%0A544RV6EUKZQIFvBl6WU%3D%0A" alt="" width="2040" height="1036" loading="lazy"></p>

<p>If you are quick-starting your advertising journey on noon, make sure to head to our <a href="/ae/adsupport/collections/quick-guides">campaign creation guides</a>.</p>

<hr>

<p style="text-align:center"><b>Need more help?</b> Email us at <a href="mailto:adsupport@noon.com">adsupport@noon.com</a></p>
<p style="text-align:center">Our dedicated support team will answer all your questions</p>
HTML;
    }
}
