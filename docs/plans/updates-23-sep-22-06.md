# PROMPT — Marketer Panel, API & Flutter Parity
# Based on: Design PDF (24 slides) + Repo audit @ commit 5ac8da4a
# Sub-Agent file — run each PROMPT section as a separate Claude Code session in VS Code
# Run in order: 11 → 12 → 13 → 14 → 15 → 16 → 17

---

## GLOBAL INVARIANTS (apply to every prompt — never violate)

```
1. All monetary values = BIGINT base-currency integers. NEVER /100 or *100 except percentage math.
2. All PKs are UUIDs with HasUuids trait.
3. Never edit or delete existing migrations — new migrations only, dated 2026_09_24_*.
4. Never run `php artisan schema:dump --prune`.
5. API guard for marketer Flutter app = `marketer_api` (JWT via tymon/jwt-auth).
6. Web panel guard = `marketer`.
7. Response format for all new API endpoints: { "success": true, "data": {...} }.
8. No JSON blobs for structured relational data.
9. All new Blade views use existing marketer layout — check resources/views/marketer/ for pattern.
10. marketer_job_assignments table does NOT exist — marketer type is now stored in marketer_marketer_job pivot.
```

---

## WHAT THE DESIGN PDF SHOWS vs WHAT EXISTS — GAP ANALYSIS

### Confirmed EXISTING (no work needed):
- Auth (login/register for وسيط/مشهور) ✅
- Notifications list (web) ✅
- Profile page (web + API) ✅
- Campaign invitations (web + API) ✅
- Active/finished campaigns (web + API) ✅
- Finance/wallet/withdrawals (web + API) ✅
- Coupon participation (web, basic API missing) ✅ web / ❌ API
- Ad slot booking (API) ✅
- Public marketer profile page (frontend + API) ✅
- Exclusive contract badge on classified view (frontend) ✅

### CONFIRMED MISSING (must build):
1. **Classified listing CRUD for marketer** (إضافة إعلان / إعلاناتي) — marketer_listings supports `listing_category=classified` in schema but no create/manage UI or classified-specific flow
2. **طلباتي (Received inquiries)** — `classified_inquiries` from clients on marketer's own ads — no marketer-side view or API
3. **Exclusive contracts read view** for marketer — admin creates them, marketer should see their own contracts (web + API)
4. **Open-market listing price override** — marketer can set own price when `allow_marketer_override=true` in `open_market_listing_prices` — UI missing
5. **API: Notifications** for marketer (Flutter app needs it — no route in `api_marketer.php`)
6. **API: Coupon participation** (marketer-side Flutter endpoint missing)
7. **API: Classified listings CRUD** (Flutter app flow matching the design)
8. **API: Received inquiries** (طلباتي for Flutter)
9. **API: Exclusive contracts list** (Flutter)
10. **Public marketer profile: exclusive_contracts field** — `MarketerProfileController::show()` does not return exclusive contracts
11. **Dashboard stats: classified conversions** — `DashboardController` only counts product conversions; `classified_inquiry` conversions exist in `marketer_campaign_conversions` but are not surfaced
12. **"مطلوب" (wanted) listings** — the PDF shows `مطلوب شقه للبيع` as a listing type; no table exists — **needs new migration** for `classified_wanted_listings` or a `listing_purpose` extension
13. **Marketer-to-client messaging** — PDF shows a chat/conversation screen; no chat table exists for marketer↔client — **needs new tables**

---

## PROMPT 11 — Classified Listing CRUD for Marketer (Web Panel)

### Context
- `marketer_listings` table already has `listing_category enum('product','travel','classified')` and `classified_listing_id FK`.
- `classified_listings` table has `seller_type` (morph) + `seller_id` — marketer can be a seller.
- `classified_categories` has `attribute_schema` JSON — dynamic attributes per category.
- From PDF: "إضافة إعلان" flow: choose category → fill attributes → upload images/video → set price → publish.
- `open_market_listing_prices` table: `classified_category_id, base_price, allow_marketer_override, min_price, max_price`.

### What to build

#### 1. Migration — add missing `classified_wanted_listings` for "مطلوب" tab

**`2026_09_24_110001_create_classified_wanted_listings_table.php`**
```php
Schema::create('classified_wanted_listings', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('listing_number', 30)->unique();
    $table->uuid('marketer_id');
    $table->uuid('classified_category_id');
    $table->uuid('country_id');
    $table->uuid('city_id')->nullable();
    $table->string('title_ar');
    $table->string('title_en')->nullable();
    $table->text('description_ar')->nullable();
    $table->bigInteger('budget_min')->nullable()->unsigned()->comment('BIGINT base-currency. No /100.');
    $table->bigInteger('budget_max')->nullable()->unsigned()->comment('BIGINT base-currency. No /100.');
    $table->string('currency', 3);
    $table->enum('status', ['active', 'fulfilled', 'cancelled', 'expired'])->default('active');
    $table->timestamp('expires_at')->nullable();
    $table->timestamps();
    $table->foreign('marketer_id')->references('id')->on('marketers')->onDelete('cascade');
    $table->foreign('classified_category_id')->references('id')->on('classified_categories')->onDelete('cascade');
    $table->foreign('country_id')->references('id')->on('countries');
    $table->foreign('city_id')->references('id')->on('cities')->onDelete('set null');
    $table->index(['marketer_id', 'status']);
});
```

#### 2. Controller — `app/Http/Controllers/Marketer/ClassifiedListingController.php` (new)

```php
namespace App\Http\Controllers\Marketer;

// Creates/manages classified_listings where seller_type='marketer', seller_id=$marketer->id
// AND creates corresponding marketer_listing (listing_category='classified') for referral tracking

class ClassifiedListingController extends Controller
{
    private function marketer(): Marketer { return Auth::guard('marketer')->user()->marketer; }

    // GET /marketer/classified-listings
    // Returns paginated classified_listings where seller_type='App\Models\Marketer' seller_id=$marketer->id
    // ALSO returns pending classified_inquiries count per listing
    public function index(Request $request): View { ... }

    // GET /marketer/classified-listings/create
    // Pass $categories = ClassifiedCategory::whereNull('parent_id')->with('children')->where('is_active',true)->get()
    // Pass $countries = Country::all()
    // Pass $openMarketPrices = OpenMarketListingPrice::pluck('base_price','classified_category_id')
    public function create(): View { ... }

    // POST /marketer/classified-listings
    // Validate: classified_category_id, country_id, title_ar, price (check against open_market_listing_prices bounds), listing_purpose, attributes (json), images (array of files)
    // Create classified_listing with seller_type = App\Models\Marketer::class, seller_id = $marketer->id
    // Create marketer_listing with listing_category='classified', classified_listing_id = $classifiedListing->id
    // Store images in classified-listings/{id}/ on public disk
    public function store(Request $request): RedirectResponse { ... }

    // GET /marketer/classified-listings/{listing}
    public function show(ClassifiedListing $listing): View { ... }

    // GET /marketer/classified-listings/{listing}/edit
    public function edit(ClassifiedListing $listing): View { ... }

    // PUT /marketer/classified-listings/{listing}
    public function update(Request $request, ClassifiedListing $listing): RedirectResponse { ... }

    // DELETE /marketer/classified-listings/{listing}
    // Soft-delete (set status='archived')
    public function destroy(ClassifiedListing $listing): RedirectResponse { ... }

    // POST /marketer/classified-listings/{listing}/pause  (toggle status active/paused)
    public function toggleStatus(ClassifiedListing $listing): RedirectResponse { ... }
}
```

**Price override logic in `store()` and `update()`:**
```php
$listingPrice = OpenMarketListingPrice::where('classified_category_id', $request->classified_category_id)->first();
if ($listingPrice) {
    if (!$listingPrice->allow_marketer_override) {
        // Force price to base_price
        $price = $listingPrice->base_price;
    } else {
        // Validate against min/max
        $rules['price'][] = 'min:' . ($listingPrice->min_price ?? 0);
        if ($listingPrice->max_price) $rules['price'][] = 'max:' . $listingPrice->max_price;
    }
}
```

#### 3. Controller — `app/Http/Controllers/Marketer/ClassifiedInquiryController.php` (new)
```php
// طلباتي — inquiries received on marketer's own classified listings
// GET /marketer/classified-inquiries → paginated ClassifiedInquiry where listing->seller_id = marketer->id
// GET /marketer/classified-inquiries/{inquiry} → show detail + mark as 'contacted'
// PATCH /marketer/classified-inquiries/{inquiry}/close → set status='closed'
```

#### 4. Controller — `app/Http/Controllers/Marketer/WantedListingController.php` (new)
```php
// طلباتي (wanted) — marketer's own "مطلوب" listings
// GET  /marketer/wanted-listings → index
// POST /marketer/wanted-listings → store (use classified_wanted_listings table)
// DELETE /marketer/wanted-listings/{wanted} → soft-delete (set status='cancelled')
```

#### 5. Routes — add to `routes/marketer.php` inside auth.marketer group:
```php
// Classified listings (open market ads)
Route::prefix('classified-listings')->name('classified-listings.')->group(function () {
    Route::get('/',                   [ClassifiedListingController::class, 'index'])->name('index');
    Route::get('/create',             [ClassifiedListingController::class, 'create'])->name('create');
    Route::post('/',                  [ClassifiedListingController::class, 'store'])->name('store');
    Route::get('/{listing}',          [ClassifiedListingController::class, 'show'])->name('show');
    Route::get('/{listing}/edit',     [ClassifiedListingController::class, 'edit'])->name('edit');
    Route::put('/{listing}',          [ClassifiedListingController::class, 'update'])->name('update');
    Route::delete('/{listing}',       [ClassifiedListingController::class, 'destroy'])->name('destroy');
    Route::post('/{listing}/toggle',  [ClassifiedListingController::class, 'toggleStatus'])->name('toggle');
});

// Received inquiries
Route::prefix('classified-inquiries')->name('classified-inquiries.')->group(function () {
    Route::get('/',                   [ClassifiedInquiryController::class, 'index'])->name('index');
    Route::get('/{inquiry}',          [ClassifiedInquiryController::class, 'show'])->name('show');
    Route::patch('/{inquiry}/close',  [ClassifiedInquiryController::class, 'close'])->name('close');
});

// Wanted listings (مطلوب)
Route::prefix('wanted-listings')->name('wanted-listings.')->group(function () {
    Route::get('/',       [WantedListingController::class, 'index'])->name('index');
    Route::get('/create', [WantedListingController::class, 'create'])->name('create');
    Route::post('/',      [WantedListingController::class, 'store'])->name('store');
    Route::delete('/{wanted}', [WantedListingController::class, 'destroy'])->name('destroy');
});

// Exclusive contracts (read-only for marketer)
Route::prefix('exclusive-contracts')->name('exclusive-contracts.')->group(function () {
    Route::get('/',          [ExclusiveContractController::class, 'index'])->name('index');
    Route::get('/{contract}',[ExclusiveContractController::class, 'show'])->name('show');
});
```

Add `ExclusiveContractController` for marketer (read-only):
```php
// app/Http/Controllers/Marketer/ExclusiveContractController.php
// index(): ExclusiveContract::where('marketer_id', $marketer->id)->with(['classifiedCategory','classifiedListing'])->latest()->paginate(20)
// show(): single contract detail
```

#### 6. Blade Views — create these files:

**`resources/views/marketer/classified-listings/index.blade.php`**
- Tab switcher: "إعلاناتي" (my listings) | "طلباتي" (received inquiries) | "مطلوب" (wanted)
- Each classified listing card: image, title, status badge, price, views count, inquiries count, edit/pause/delete actions
- "إضافة إعلان" button links to create

**`resources/views/marketer/classified-listings/create.blade.php`**
- Step 1: Category selection (icon grid matching PDF: عقارات, السيارات, عروض سفر, أرقام جوال, أرقام لوحات, خدمات أخرى)
- Step 2: Dynamic attributes form (loaded via Alpine.js from category's `attribute_schema` JSON)
- Step 3: Price + listing_purpose (بيع/إيجار) + negotiable toggle
- Step 4: Images/video upload (FilePond)
- Price field: show base price if override not allowed; show bounded input if override allowed

**`resources/views/marketer/classified-listings/show.blade.php`**
- Listing detail + inline inquiries list

**`resources/views/marketer/classified-inquiries/index.blade.php`**
- List of inquiries with client contact info, message, status badges

**`resources/views/marketer/wanted-listings/index.blade.php`**
- Cards for wanted listings (budget range, category, status)

**`resources/views/marketer/exclusive-contracts/index.blade.php`**
- Table: category/listing, starts_at, ends_at, status badge, download contract file link

#### 7. Sidebar navigation — update marketer sidebar to add:
```blade
{{-- in the marketer navigation component --}}
<a href="{{ route('marketer.classified-listings.index') }}">إعلانات السوق المفتوح</a>
<a href="{{ route('marketer.exclusive-contracts.index') }}">العقود الحصرية</a>
<a href="{{ route('marketer.coupon-participation.index') }}">الاشتراك في القسائم</a>
```

#### 8. Lang keys (lang/en/marketer.php + lang/ar/marketer.php):
```
'classified_listings'      => 'Open Market Listings' / 'إعلانات السوق المفتوح'
'add_classified_listing'   => 'Add Listing' / 'إضافة إعلان'
'my_listings'              => 'My Listings' / 'إعلاناتي'
'received_inquiries'       => 'Received Inquiries' / 'طلباتي'
'wanted_listings'          => 'Wanted' / 'مطلوب'
'exclusive_contracts'      => 'Exclusive Contracts' / 'العقود الحصرية'
'listing_purpose_sale'     => 'For Sale' / 'للبيع'
'listing_purpose_rent'     => 'For Rent' / 'للإيجار'
'price_negotiable'         => 'Price Negotiable' / 'قابل للتفاوض'
'price_override_not_allowed' => 'Price is fixed by platform.' / 'السعر محدد من المنصة.'
'inquiry_from'             => 'Inquiry from' / 'طلب من'
'mark_as_contacted'        => 'Mark as Contacted' / 'تم التواصل'
'close_inquiry'            => 'Close' / 'إغلاق'
'budget_range'             => 'Budget Range' / 'النطاق السعري'
'contract_active'          => 'Active' / 'نشط'
'contract_expired'         => 'Expired' / 'منتهي'
'contract_pending'         => 'Pending' / 'قيد الانتظار'
'download_contract'        => 'Download Contract' / 'تنزيل العقد'
```

---

## PROMPT 12 — Marketer → Client Messaging (Conversations)

### Context
The PDF shows a full chat screen (المحادثات) with message bubbles and a conversation list. No chat tables exist in the current schema. The closest system is `dispute_messages` and `ticket_messages` — use same pattern.

### Migrations

**`2026_09_24_120001_create_marketer_conversations_table.php`**
```php
Schema::create('marketer_conversations', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('marketer_id');
    $table->uuid('customer_id');
    $table->uuid('classified_listing_id')->nullable()->comment('Context: which listing triggered this conversation');
    $table->uuid('classified_inquiry_id')->nullable()->comment('Links to the inquiry if conversation started from one');
    $table->timestamp('last_message_at')->nullable();
    $table->boolean('marketer_has_unread')->default(false);
    $table->boolean('customer_has_unread')->default(false);
    $table->timestamps();
    $table->unique(['marketer_id', 'customer_id', 'classified_listing_id']);
    $table->foreign('marketer_id')->references('id')->on('marketers')->onDelete('cascade');
    $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
    $table->foreign('classified_listing_id')->references('id')->on('classified_listings')->onDelete('set null');
    $table->index(['marketer_id', 'last_message_at']);
    $table->index(['customer_id', 'last_message_at']);
});
```

**`2026_09_24_120002_create_marketer_conversation_messages_table.php`**
```php
Schema::create('marketer_conversation_messages', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('conversation_id');
    $table->enum('sender_type', ['marketer', 'customer']);
    $table->uuid('sender_id');
    $table->text('body');
    $table->string('attachment_path')->nullable();
    $table->timestamp('read_at')->nullable();
    $table->timestamp('created_at')->useCurrent();
    $table->foreign('conversation_id')->references('id')->on('marketer_conversations')->onDelete('cascade');
    $table->index(['conversation_id', 'created_at']);
});
```

### Models
**`app/Models/MarketerConversation.php`** — standard with `HasUuids`. Add:
```php
public const UPDATED_AT = null; // override if no updated_at needed — but keep both for simplicity
public function marketer(): BelongsTo { return $this->belongsTo(Marketer::class); }
public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
public function messages(): HasMany { return $this->hasMany(MarketerConversationMessage::class, 'conversation_id')->orderBy('created_at'); }
public function latestMessage(): HasOne { return $this->hasOne(MarketerConversationMessage::class, 'conversation_id')->latestOfMany(); }
public function classifiedListing(): BelongsTo { return $this->belongsTo(ClassifiedListing::class); }
```

**`app/Models/MarketerConversationMessage.php`** — `HasUuids`, `public const UPDATED_AT = null`.

### Web Controller — `app/Http/Controllers/Marketer/ConversationController.php`
```php
// GET /marketer/conversations → list conversations ordered by last_message_at desc
// GET /marketer/conversations/{conversation} → show messages, mark marketer_has_unread=false
// POST /marketer/conversations/{conversation}/messages → send message (sender_type='marketer')
// POST /marketer/conversations → start new conversation (from classified inquiry context)
```

### Routes (add inside auth.marketer group in routes/marketer.php):
```php
Route::prefix('conversations')->name('conversations.')->group(function () {
    Route::get('/',                           [ConversationController::class, 'index'])->name('index');
    Route::post('/',                          [ConversationController::class, 'store'])->name('store');
    Route::get('/{conversation}',             [ConversationController::class, 'show'])->name('show');
    Route::post('/{conversation}/messages',   [ConversationController::class, 'sendMessage'])->name('message');
});
```

### Blade Views
**`resources/views/marketer/conversations/index.blade.php`**  
Two-panel layout (matching PDF):
- Left: conversation list with avatar, name, last message snippet, unread dot, timestamp
- Right: message thread with bubble UI (marketer messages right-aligned teal, client messages left grey)
- Input bar at bottom with send button

**Integration point:** In `resources/views/marketer/classified-inquiries/show.blade.php`, add a "بدء محادثة" button that creates/opens conversation with this inquiry's customer.

### Lang keys:
```
'conversations'          => 'Conversations' / 'المحادثات'
'start_conversation'     => 'Start Conversation' / 'بدء محادثة'
'type_message'           => 'Type here...' / 'اكتب هنا...'
'no_conversations'       => 'No conversations yet.' / 'لا توجد محادثات حتى الآن.'
'customer_service'       => 'Customer Service' / 'خدمة عملاء ناوي'
```

---

## PROMPT 13 — Marketer Flutter API: All Missing Endpoints

### Context
The Flutter marketer app uses `routes/api_marketer.php` with JWT guard `marketer_api`. All controllers go in `app/Http/Controllers/Api/Marketer/`. Current gaps: no notifications, no classified listings, no inquiries, no conversations, no exclusive contracts, no coupon participation.

### Add to `routes/api_marketer.php` inside the auth middleware group:

```php
// ── Notifications ─────────────────────────────────────────────────────────
Route::prefix('notifications')->name('marketer.api.notifications.')->group(function () {
    Route::get('/',                  [NotificationController::class, 'index'])->name('index');
    Route::get('/unread-count',      [NotificationController::class, 'unreadCount'])->name('unread-count');
    Route::post('/mark-all-read',    [NotificationController::class, 'markAllRead'])->name('mark-all-read');
    Route::post('/{id}/read',        [NotificationController::class, 'markRead'])->name('mark-read');
});

// ── Classified Listings (open market) ─────────────────────────────────────
Route::prefix('classified-listings')->name('marketer.api.classified.')->group(function () {
    Route::get('/',             [ClassifiedListingController::class, 'index'])->name('index');
    Route::post('/',            [ClassifiedListingController::class, 'store'])->name('store');
    Route::get('/{id}',         [ClassifiedListingController::class, 'show'])->name('show');
    Route::put('/{id}',         [ClassifiedListingController::class, 'update'])->name('update');
    Route::delete('/{id}',      [ClassifiedListingController::class, 'destroy'])->name('destroy');
    Route::post('/{id}/toggle', [ClassifiedListingController::class, 'toggleStatus'])->name('toggle');
});

// ── Received Inquiries (طلباتي) ───────────────────────────────────────────
Route::prefix('classified-inquiries')->name('marketer.api.inquiries.')->group(function () {
    Route::get('/',            [ClassifiedInquiryController::class, 'index'])->name('index');
    Route::get('/{id}',        [ClassifiedInquiryController::class, 'show'])->name('show');
    Route::patch('/{id}/close',[ClassifiedInquiryController::class, 'close'])->name('close');
});

// ── Wanted Listings (مطلوب) ───────────────────────────────────────────────
Route::prefix('wanted-listings')->name('marketer.api.wanted.')->group(function () {
    Route::get('/',       [WantedListingController::class, 'index'])->name('index');
    Route::post('/',      [WantedListingController::class, 'store'])->name('store');
    Route::delete('/{id}',[WantedListingController::class, 'destroy'])->name('destroy');
});

// ── Exclusive Contracts (read-only) ───────────────────────────────────────
Route::prefix('exclusive-contracts')->name('marketer.api.contracts.')->group(function () {
    Route::get('/',      [ExclusiveContractController::class, 'index'])->name('index');
    Route::get('/{id}',  [ExclusiveContractController::class, 'show'])->name('show');
});

// ── Coupon Participation ───────────────────────────────────────────────────
Route::prefix('coupon-participation')->name('marketer.api.coupon.')->group(function () {
    Route::get('/',               [CouponParticipationController::class, 'index'])->name('index');
    Route::post('/{invitation}',  [CouponParticipationController::class, 'store'])->name('store');
});

// ── Conversations ──────────────────────────────────────────────────────────
Route::prefix('conversations')->name('marketer.api.conversations.')->group(function () {
    Route::get('/',                         [ConversationController::class, 'index'])->name('index');
    Route::post('/',                        [ConversationController::class, 'store'])->name('store');
    Route::get('/{id}',                     [ConversationController::class, 'show'])->name('show');
    Route::post('/{id}/messages',           [ConversationController::class, 'sendMessage'])->name('message');
    Route::post('/{id}/messages/read',      [ConversationController::class, 'markRead'])->name('read');
});

// ── Dashboard (enhance existing) ───────────────────────────────────────────
// (add classified_conversions_count to existing /dashboard endpoint — see PROMPT 14)
```

### New API Controllers (all in `app/Http/Controllers/Api/Marketer/`)

**`NotificationController.php`**
```php
// Reuse the marketer web notification pattern. Guard: marketer_api.
// index(): auth()->guard('marketer_api')->user()->notifications()->latest()->paginate(20)
//          Return: { data: [ {id, type, title, body, read_at, created_at} ] }
// unreadCount(): { data: { count: $user->unreadNotifications()->count() } }
// markAllRead(): $user->unreadNotifications->markAsRead()
// markRead($id): $user->notifications()->findOrFail($id)->markAsRead()
```

**`ClassifiedListingController.php`** (API version)
```php
// index(): ClassifiedListing::where('seller_type', Marketer::class)->where('seller_id', $marketer->id)
//          ->with(['classifiedCategory:id,name_ar','country:id,name_ar'])
//          ->when($request->status, fn($q) => $q->where('status', $request->status))
//          ->latest()->paginate(20)
//          Response shape: { id, listing_number, title_ar, price, currency, status, views_count, 
//                            category: {id, name_ar}, images: [...], exclusive_contract: {marketer_name, expires_at}|null }

// store(): same validation as web controller + create classified_listing + marketer_listing
// Price override check: read OpenMarketListingPrice for the category

// show(): single listing with full detail including classified_inquiries_count

// update(), destroy(), toggleStatus() — ownership check: abort_if($listing->seller_id !== $marketer->id, 403)
```

**`ClassifiedInquiryController.php`** (API version)
```php
// index(): ClassifiedInquiry::whereHas('classifiedListing', fn($q) => 
//              $q->where('seller_type', Marketer::class)->where('seller_id', $marketer->id))
//          ->with(['classifiedListing:id,title_ar', 'customer:id,name'])
//          ->latest()->paginate(20)
//          Response: { id, message, contact_phone, status, listing:{id,title_ar}, customer:{name}, created_at }

// show(): full detail, mark as 'contacted' if currently 'new'
// close(): set status='closed'
```

**`WantedListingController.php`** (API version)
```php
// Standard CRUD on classified_wanted_listings for the authenticated marketer
// Response shape: { id, title_ar, category:{name_ar}, budget_min, budget_max, currency, status, expires_at }
```

**`ExclusiveContractController.php`** (API version, read-only)
```php
// index(): ExclusiveContract::where('marketer_id', $marketer->id)
//          ->with(['classifiedCategory:id,name_ar', 'classifiedListing:id,title_ar'])
//          ->orderBy('ends_at','desc')->paginate(20)
//          Response: { id, scope: 'category'|'listing', category_name, listing_title, starts_at, ends_at, 
//                      status, has_file: bool, contract_file_url: signed-url|null }

// show(): single contract + signed URL for contract_file_path if set
//         $url = $contract->contract_file_path ? Storage::disk('private')->temporaryUrl($contract->contract_file_path, now()->addHours(1)) : null
```

**`CouponParticipationController.php`** (API version — marketer side)
```php
// index(): same logic as web CouponParticipationController::index() but JSON response
//          CouponParticipationInvitation::where('status','open')
//          ->where('registration_deadline', '>', now())
//          ->with(['coupon:id,code,name,type,value'])
//          ->paginate(20)
//          Also include: marketer's own request status for each invitation
//          Response: { id, title, description, max_participants, approved_count, min_fee_amount, currency,
//                      registration_deadline, coupon:{code,name,discount}, my_request: {status,offered_fee}|null,
//                      wallet_balance: int }

// store($invitationId): validate offered_fee_amount >= min_fee_amount, payment_method in [wallet, bank_transfer]
//          If wallet: check balance upfront, return 422 if insufficient
//          Create CouponParticipationRequest
//          Response: { success: true, message: 'تم إرسال طلبك بنجاح.', data: {request_id, status} }
```

**`ConversationController.php`** (API version)
```php
// index(): MarketerConversation::where('marketer_id', $marketer->id)
//          ->with(['latestMessage', 'customer:id,name,avatar_path'])
//          ->orderByDesc('last_message_at')->paginate(20)
//          Response: { id, customer:{name, avatar_url}, last_message:{body, sender_type, created_at},
//                      marketer_has_unread, listing_title|null }

// store(): find or create conversation for (marketer, customer, listing)
//          Body: { customer_id, classified_listing_id? }

// show($id): messages paginated (cursor-based, latest first)
//          Mark marketer_has_unread=false
//          Response: { conversation: {...}, messages: [{id,body,sender_type,created_at,read_at},...] }

// sendMessage($id): validate body, create MarketerConversationMessage
//          Update conversation last_message_at and customer_has_unread=true
//          Broadcast event if Laravel Reverb is active

// markRead($id): mark all unread messages as read for this marketer
```

### Dashboard enhancement (existing `DashboardController.php`):
Add to the stats array:
```php
$classifiedConversions = MarketerCampaignConversion::whereIn('invitation_id', $invitationIds)
    ->where('conversion_type', 'classified_inquiry')->count();

$classifiedListingsCount = ClassifiedListing::where('seller_type', Marketer::class)
    ->where('seller_id', $marketer->id)->where('status', 'active')->count();

$unreadInquiries = ClassifiedInquiry::whereHas('classifiedListing', fn($q) =>
    $q->where('seller_type', Marketer::class)->where('seller_id', $marketer->id))
    ->where('status', 'new')->count();

$unreadMessages = MarketerConversation::where('marketer_id', $marketer->id)
    ->where('marketer_has_unread', true)->count();
```

Return these in the API dashboard response's `data` object.

---

## PROMPT 14 — Public Marketer Profile API: Exclusive Contracts + Classified Listings

### Context
`app/Http/Controllers/Api/Public/MarketerProfileController.php` exists. The `show()` method returns profile data but does NOT include:
- Marketer's exclusive contracts (frontend already expects `exclusive_contract` field on classified listings)
- Marketer's active classified listings
- Marketer's jobs (types: مشهور/وسيط/مشهور عادي) — **already included via marketerJobs relation**

### Changes to `MarketerProfileController::show()`

In the existing `show()` method, add to the response:
```php
// After loading $profile...

// Exclusive contracts for this marketer (active ones visible to public)
$exclusiveContracts = \App\Models\ExclusiveContract::where('marketer_id', $marketer->id)
    ->where('status', 'active')
    ->where('ends_at', '>=', now())
    ->with(['classifiedCategory:id,name_ar,name_en', 'classifiedListing:id,title_ar'])
    ->get()
    ->map(fn ($c) => [
        'scope'         => $c->classified_listing_id ? 'listing' : 'category',
        'category_name' => $c->classifiedCategory?->name_ar,
        'listing_title' => $c->classifiedListing?->title_ar,
        'ends_at'       => $c->ends_at?->toIso8601String(),
    ]);

// Active classified listings by this marketer (open market ads)
$classifiedListings = \App\Models\ClassifiedListing::where('seller_type', \App\Models\Marketer::class)
    ->where('seller_id', $marketer->id)
    ->where('status', 'active')
    ->with(['classifiedCategory:id,name_ar', 'country:id,name_ar'])
    ->latest()
    ->limit(12)
    ->get()
    ->map(fn ($l) => [
        'id'            => $l->id,
        'listing_number'=> $l->listing_number,
        'title_ar'      => $l->title_ar,
        'price'         => $l->price,
        'currency'      => $l->currency,
        'price_negotiable' => (bool) $l->price_negotiable,
        'category'      => ['name_ar' => $l->classifiedCategory?->name_ar],
        'first_image'   => null, // TODO: add classified_listing_images join when images table is clarified
        'listing_purpose' => $l->listing_purpose,
        'views_count'   => $l->views_count,
    ]);

// Add to the show() return array:
'exclusive_contracts'   => $exclusiveContracts,
'classified_listings'   => $classifiedListings,
'classified_count'      => $classifiedListings->count(),
```

### Also update the `index()` response (marketer card in list):
Add `'classified_count' => $marketer->classifiedListingsCount ?? 0` after joining:
```php
// Add withCount to the query:
->withCount(['classifiedListings' => fn($q) => $q->where('status','active')]) // join via seller morph
```
Note: this requires adding a `classifiedListings()` HasMany or MorphMany on the `Marketer` model:
```php
// In app/Models/Marketer.php — add:
public function classifiedListings(): HasMany
{
    return $this->hasMany(ClassifiedListing::class, 'seller_id')
        ->where('seller_type', static::class);
}
```

---

## PROMPT 15 — Ad Packages (أنظمة الإعلانات / باقات ناوي) — Web + API

### Context
The PDF shows "أنظمة الإعلانات — باقات ناوي" and "أنظمة الإعلانات — الوسطاء" screens. These are subscription/ad-boost package purchase flows showing:
- Package name, price breakdown (base + VAT = total)
- Payment method (Visa, STC Pay)
- Contract terms (multi-page PDF-like text)
- Confirm + "ابدأ الدعم" (Start Boosting)
- After purchase: "تم الدفع بنجاح" + "تحقق من إعلاناتك"

The existing `PromoteController` + `PromoteBookingController` handle a promote-booking flow. Check what's already there before adding anything:

```bash
cat app/Http/Controllers/Marketer/PromoteController.php | head -60
cat app/Http/Controllers/Marketer/PromoteBookingController.php | head -60
ls resources/views/marketer/promote/
grep -n "promote\|ad-package\|باقة" routes/marketer.php
```

**Before writing any code, run the above commands.** Then:

#### If PromoteController only handles ad-slot bookings (PaidAdBooking) but NOT subscription packages:
Create:

**`2026_09_24_150001_create_marketer_ad_packages_table.php`**
```php
Schema::create('marketer_ad_packages', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('name_ar');
    $table->string('name_en')->nullable();
    $table->text('description_ar')->nullable();
    $table->unsignedBigInteger('price')->comment('BIGINT base-currency. No /100.');
    $table->string('currency', 3);
    $table->unsignedTinyInteger('vat_pct')->default(15)->comment('VAT percentage 0-100');
    $table->enum('target_type', ['influencer', 'affiliate', 'broker', 'all'])->default('all');
    $table->unsignedInteger('duration_days');
    $table->json('features')->nullable()->comment('Array of feature strings shown in package UI');
    $table->boolean('is_active')->default(true);
    $table->unsignedInteger('sort_order')->default(0);
    $table->timestamps();
});
```

**`2026_09_24_150002_create_marketer_ad_package_subscriptions_table.php`**
```php
Schema::create('marketer_ad_package_subscriptions', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('marketer_id');
    $table->uuid('package_id');
    $table->unsignedBigInteger('amount_paid')->comment('BIGINT. No /100.');
    $table->unsignedBigInteger('vat_amount')->comment('BIGINT. No /100.');
    $table->string('currency', 3);
    $table->enum('payment_method', ['wallet', 'bank_transfer', 'online'])->default('wallet');
    $table->string('payment_proof_path')->nullable();
    $table->enum('status', ['pending', 'active', 'expired', 'cancelled'])->default('pending');
    $table->timestamp('starts_at')->nullable();
    $table->timestamp('expires_at')->nullable();
    $table->timestamps();
    $table->foreign('marketer_id')->references('id')->on('marketers')->onDelete('cascade');
    $table->foreign('package_id')->references('id')->on('marketer_ad_packages')->onDelete('restrict');
    $table->index(['marketer_id', 'status']);
});
```

**`app/Http/Controllers/Marketer/AdPackageController.php`** (new):
```php
// GET /marketer/ad-packages → index: list active packages appropriate for this marketer's type
//     Pass $packages = MarketerAdPackage::where('is_active',true)
//               ->where(fn($q)=> $q->where('target_type', $marketerJobKey)->orWhere('target_type','all'))
//               ->ordered()->get()
//     Pass $activeSubscription = MarketerAdPackageSubscription::where('marketer_id',$marketer->id)->where('status','active')->first()
//     Pass $walletBalance = WalletService::getOrCreateWallet('marketer',$marketer->id,$currency)->balance

// POST /marketer/ad-packages/{package}/subscribe
//     Validate payment_method, payment_proof (required when bank_transfer)
//     If wallet: debit via WalletService; set status=active, starts_at=now(), expires_at=now()+duration_days
//     If bank_transfer: status=pending, store proof, admin reviews
//     Redirect to success page
```

Routes (add to routes/marketer.php):
```php
Route::prefix('ad-packages')->name('ad-packages.')->group(function () {
    Route::get('/',                            [AdPackageController::class, 'index'])->name('index');
    Route::get('/contract/{package}',          [AdPackageController::class, 'contract'])->name('contract');
    Route::post('/{package}/subscribe',        [AdPackageController::class, 'subscribe'])->name('subscribe');
    Route::get('/success',                     [AdPackageController::class, 'success'])->name('success');
});
```

API routes (in api_marketer.php):
```php
Route::prefix('ad-packages')->name('marketer.api.packages.')->group(function () {
    Route::get('/',                  [AdPackageApiController::class, 'index'])->name('index');
    Route::post('/{id}/subscribe',   [AdPackageApiController::class, 'subscribe'])->name('subscribe');
    Route::get('/my-subscription',   [AdPackageApiController::class, 'mySubscription'])->name('current');
});
```

`app/Http/Controllers/Api/Marketer/AdPackageController.php`:
```php
// index(): packages + wallet balance
// subscribe(): same debit/pending logic as web, returns { success, data: { subscription_id, status, expires_at } }
// mySubscription(): current active subscription or null
```

Blade views:
- `resources/views/marketer/ad-packages/index.blade.php` — package cards matching PDF design (price breakdown with VAT, feature list, subscribe button)
- `resources/views/marketer/ad-packages/contract.blade.php` — full terms text, confirm + pay buttons
- `resources/views/marketer/ad-packages/success.blade.php` — success checkmark + "تحقق من إعلاناتك" button

Admin management (add to Admin package):
- `app/Http/Controllers/Admin/MarketerAdPackageController.php` — CRUD for packages
- Add `admin/marketer-ad-packages` resource route in `routes/admin.php`
- `resources/views/admin/marketer-ad-packages/` — index + form views

Lang keys:
```
'ad_packages'            => 'Ad Packages' / 'باقات الإعلانات'
'nawy_packages'          => 'Nawy Packages' / 'باقات ناوي'
'package_price'          => 'Price' / 'السعر'
'vat_included'           => 'VAT (15%)' / 'ضريبة القيمة المضافة'
'total_with_vat'         => 'Total' / 'المجموع'
'subscribe_now'          => 'Start Boosting' / 'ابدأ الدعم'
'package_active'         => 'Package Active Until' / 'الباقة نشطة حتى'
'payment_success'        => 'Payment Successful' / 'تم الدفع بنجاح'
'check_listings'         => 'Check Your Listings' / 'تحقق من إعلاناتك'
```

---

## PROMPT 16 — Marketer Registration: Two Flows (وسيط / مشهور) — API + Web

### Context
The PDF shows two distinct registration flows:
1. **وسيط (Broker/Affiliate)**: chooses specialties (عقارات, سيارات, عروض سفر, أرقام جوال, أرقام لوحات, خدمات أخرى), uploads CV + certifications, fills profile
2. **مشهور (Influencer)**: social media links (Instagram, Snapchat), bio, gender field, profile/banner images

Both start from "اختر نوع الحساب" screen. Currently `AuthController::register()` exists but may not handle the two-step job selection.

**First, read the existing register flow:**
```bash
cat app/Http/Controllers/Marketer/AuthController.php | grep -A 30 "register"
cat app/Http/Controllers/Api/Marketer/AuthController.php | grep -A 30 "register"
cat resources/views/marketer/auth/ -r
```

**After reading**, add what's missing:

#### 1. Registration step-completion API endpoints

`app/Http/Controllers/Api/Marketer/OnboardingController.php` (new):
```php
// POST /api/marketer/onboarding/job-type
//   Body: { job_keys: ['broker'|'influencer'|'affiliate'] }
//   Creates/updates marketer_marketer_job pivot rows
//   Returns: { success: true, next_step: 'profile' }

// POST /api/marketer/onboarding/profile
//   Body: { specialty_ar, specialty_en, bio_ar, bio_en, social_links, broker_category_id, broker_city_id,
//            broker_serves_all_cities, avatar (file), banner (file) }
//   Updates MarketerProfile
//   If influencer: validates social_links has at least one platform
//   If broker: validates broker_category_id required
//   Returns: { success: true, marketer: { id, name, onboarding_completed_at } }

// POST /api/marketer/onboarding/complete
//   Sets marketer.onboarding_completed_at = now()
//   Returns: { success: true, token: JWT }
```

Routes (add before the auth middleware in api_marketer.php — or inside with `marketer.api.auth` but `!marketer.api.active` check):
```php
Route::middleware('marketer.api.auth')->prefix('onboarding')->group(function () {
    Route::post('/job-type', [OnboardingController::class, 'jobType']);
    Route::post('/profile',  [OnboardingController::class, 'profile']);
    Route::post('/complete', [OnboardingController::class, 'complete']);
});
```

#### 2. Web registration — "اختر نوع الحساب" step

In `resources/views/marketer/auth/register.blade.php` (update, don't replace):
After basic info is submitted, redirect to a job-type selection step if `onboarding_completed_at` is null:

`app/Http/Controllers/Marketer/OnboardingController.php` (new):
```php
// GET  /marketer/onboarding → show step based on what's missing
// POST /marketer/onboarding/job-type → save marketer job selection, redirect to profile step
// GET  /marketer/onboarding/profile → profile completion form (split by job type)
// POST /marketer/onboarding/profile → save profile, complete onboarding
```

Add route (routes/marketer.php, inside auth.marketer group but before `onboarding_completed` middleware if one exists):
```php
Route::prefix('onboarding')->name('onboarding.')->middleware('marketer.auth')->group(function () {
    Route::get('/',                 [OnboardingController::class, 'index'])->name('index');
    Route::post('/job-type',        [OnboardingController::class, 'saveJobType'])->name('job-type');
    Route::get('/profile',          [OnboardingController::class, 'profileForm'])->name('profile');
    Route::post('/profile',         [OnboardingController::class, 'saveProfile'])->name('profile.save');
});
```

Blade views:
- `resources/views/marketer/onboarding/job-type.blade.php` — icon grid: مشهور عادي / وسيط / مشهور (matching PDF)
- `resources/views/marketer/onboarding/profile-influencer.blade.php` — social links, bio, gender, avatar/banner
- `resources/views/marketer/onboarding/profile-broker.blade.php` — specialty categories, city, CV upload

Lang keys:
```
'choose_account_type'    => 'Choose Account Type' / 'اختر نوع الحساب'
'influencer_regular'     => 'Regular User' / 'مستخدم عادي'
'influencer'             => 'Influencer' / 'مشهور'
'broker'                 => 'Broker' / 'وسيط'
'social_instagram'       => 'Instagram' / 'إنستغرام'
'social_snapchat'        => 'Snapchat' / 'سناب شات'
'social_tiktok'          => 'TikTok' / 'تيك توك'
'specialty_areas'        => 'Specialty Areas' / 'التخصصات'
'cv_upload'              => 'Upload CV' / 'أرفق السيرة الذاتية'
'certifications_upload'  => 'Upload Certifications' / 'أرفق الشهادات'
'complete_profile'       => 'Complete Your Profile' / 'أكمل بياناتك'
```

---

## PROMPT 17 — QC Pass + Frontend Integration Checks

### Part A: Backend verification commands

Run ALL of these and fix any failures before considering done:

```bash
# 1. PHP syntax check on ALL new files
find app/ -name "*.php" -newer database/migrations/2026_09_22_190000_create_marketer_jobs_table.php | xargs php -l 2>&1 | grep -v "No syntax errors"

# 2. No hardcoded Arabic strings in new controllers
grep -rn "[ا-ي]" app/Http/Controllers/Marketer/ClassifiedListingController.php \
    app/Http/Controllers/Api/Marketer/ClassifiedListingController.php \
    app/Http/Controllers/Api/Marketer/ConversationController.php \
    app/Http/Controllers/Api/Marketer/NotificationController.php 2>/dev/null | grep -v "comment\|//"

# 3. No monetary /100 bugs
grep -rn "/ 100\b" app/Http/Controllers/Marketer/ app/Http/Controllers/Api/Marketer/ --include="*.php"

# 4. All new migrations have UUID primary keys
grep -L "uuid\|HasUuids" database/migrations/2026_09_24_*.php 2>/dev/null

# 5. All new models have HasUuids
find app/Models -name "MarketerConversation*.php" -o -name "ClassifiedWanted*.php" -o -name "MarketerAdPackage*.php" | xargs grep -L "HasUuids" 2>/dev/null

# 6. Route list sanity
php artisan route:list --path=marketer/classified-listings
php artisan route:list --path=marketer/conversations
php artisan route:list --path=marketer/ad-packages
php artisan route:list --path=api/marketer/classified-listings
php artisan route:list --path=api/marketer/notifications
php artisan route:list --path=api/marketer/conversations

# 7. No editing of old migrations
git diff --name-only database/migrations/ | grep -v "2026_09_24_" | grep -v "2026_09_23_"

# 8. Verify new tables exist after migrate
php artisan migrate --step
php artisan tinker --execute="Schema::hasTable('marketer_conversations') && Schema::hasTable('classified_wanted_listings') && Schema::hasTable('marketer_ad_packages') ? 'OK' : 'MISSING'"
```

### Part B: Frontend (Next.js) checks

Read the following files and verify they handle the new API fields:

```bash
cat frontend/src/features/classified/classified-view/classified-header-details.tsx | grep -A 10 "exclusiveContract"
cat frontend/src/features/noon/marketer-profile/api.ts | grep -A 5 "classified_listings\|exclusive_contracts"
```

**If `marketer-profile/api.ts` does NOT consume `classified_listings` or `exclusive_contracts`:**
Update `frontend/src/features/noon/marketer-profile/helpers/types.ts` to add:
```typescript
exclusive_contracts?: Array<{
  scope: 'category' | 'listing';
  category_name?: string;
  listing_title?: string;
  ends_at?: string;
}>;
classified_listings?: Array<{
  id: string;
  listing_number: string;
  title_ar: string;
  price: number;
  currency: string;
  price_negotiable: boolean;
  category: { name_ar: string };
  listing_purpose: 'sale' | 'rent';
  views_count: number;
}>;
classified_count?: number;
```

And update `frontend/src/features/noon/marketer-profile/index.tsx` to render:
1. A "إعلانات السوق المفتوح" section showing `classified_listings` as cards (image, title, price)
2. A small "عقود حصرية نشطة" badge/row if `exclusive_contracts.length > 0`

**If the classified-view already shows exclusive contract badge (confirmed in audit):** just verify the badge text is translated — the current code shows `"Exclusive contract"` in English. Update to use i18n:
```tsx
// classified-header-details.tsx — replace:
"Exclusive contract"
// with:
t('exclusive_contract') // or the Arabic equivalent
```

### Part C: Sidebar + navigation completeness

In `resources/views/marketer/` find the sidebar/layout file and confirm ALL new routes have nav entries:

```bash
find resources/views/marketer -name "*.php" | xargs grep -l "sidebar\|nav-link\|Route::" 2>/dev/null | head -3
```

Read that file and add the missing items:
```blade
{{-- Required nav items (add if missing) --}}
<a href="{{ route('marketer.classified-listings.index') }}" class="...">
    <x-icon name="home" /> {{ __('marketer.classified_listings') }}
</a>
<a href="{{ route('marketer.conversations.index') }}" class="...">
    <x-icon name="chat" /> {{ __('marketer.conversations') }}
    @if($unreadCount > 0)<span class="badge">{{ $unreadCount }}</span>@endif
</a>
<a href="{{ route('marketer.exclusive-contracts.index') }}" class="...">
    <x-icon name="document" /> {{ __('marketer.exclusive_contracts') }}
</a>
<a href="{{ route('marketer.ad-packages.index') }}" class="...">
    <x-icon name="star" /> {{ __('marketer.ad_packages') }}
</a>
<a href="{{ route('marketer.coupon-participation.index') }}" class="...">
    <x-icon name="ticket" /> {{ __('marketer.coupon_participation') }}
</a>
```

To inject `$unreadCount` into the layout, add to the base marketer layout's view composer or middleware:
```php
// In AppServiceProvider::boot() or a dedicated ViewServiceProvider:
View::composer('marketer.*', function ($view) {
    if (auth('marketer')->check()) {
        $marketer = auth('marketer')->user()->marketer;
        $view->with('unreadMessages', \App\Models\MarketerConversation::where('marketer_id', $marketer->id)->where('marketer_has_unread', true)->count());
        $view->with('unreadInquiries', \App\Models\ClassifiedInquiry::whereHas('classifiedListing', fn($q)=> $q->where('seller_type',\App\Models\Marketer::class)->where('seller_id',$marketer->id))->where('status','new')->count());
    }
});
```

### Part D: Flutter API Contract Summary

After all prompts are applied, this is the complete Flutter marketer app API surface. Verify each endpoint returns `{ "success": true, "data": {...} }`:

| Endpoint | Method | Description |
|---|---|---|
| `/api/marketer/login` | POST | Auth |
| `/api/marketer/me` | GET | Current user |
| `/api/marketer/dashboard` | GET | Stats (now includes classified, conversations) |
| `/api/marketer/profile` | GET/POST | Profile read/update |
| `/api/marketer/notifications` | GET | Paginated notifications |
| `/api/marketer/notifications/unread-count` | GET | Unread count |
| `/api/marketer/notifications/mark-all-read` | POST | Mark all read |
| `/api/marketer/invitations` | GET | Campaign invitations |
| `/api/marketer/invitations/{id}/accept` | POST | Accept |
| `/api/marketer/invitations/{id}/reject` | POST | Reject |
| `/api/marketer/campaigns/active` | GET | Active campaigns |
| `/api/marketer/campaigns/finished` | GET | Finished campaigns |
| `/api/marketer/listings` | GET | Product listings |
| `/api/marketer/classified-listings` | GET | Classified listings |
| `/api/marketer/classified-listings` | POST | Create classified listing |
| `/api/marketer/classified-listings/{id}` | GET/PUT/DELETE | CRUD |
| `/api/marketer/classified-listings/{id}/toggle` | POST | Toggle status |
| `/api/marketer/classified-inquiries` | GET | Received inquiries |
| `/api/marketer/classified-inquiries/{id}` | GET | Detail |
| `/api/marketer/classified-inquiries/{id}/close` | PATCH | Close |
| `/api/marketer/wanted-listings` | GET/POST | Wanted listings CRUD |
| `/api/marketer/exclusive-contracts` | GET | My contracts |
| `/api/marketer/exclusive-contracts/{id}` | GET | Detail + download URL |
| `/api/marketer/coupon-participation` | GET | Open invitations |
| `/api/marketer/coupon-participation/{invitation}` | POST | Submit request |
| `/api/marketer/conversations` | GET | All conversations |
| `/api/marketer/conversations` | POST | Start conversation |
| `/api/marketer/conversations/{id}` | GET | Messages |
| `/api/marketer/conversations/{id}/messages` | POST | Send message |
| `/api/marketer/conversations/{id}/messages/read` | POST | Mark read |
| `/api/marketer/ad-packages` | GET | Available packages |
| `/api/marketer/ad-packages/{id}/subscribe` | POST | Subscribe |
| `/api/marketer/ad-packages/my-subscription` | GET | Active sub |
| `/api/marketer/finance/commissions` | GET | Commission history |
| `/api/marketer/finance/wallet` | GET | Wallet balance |
| `/api/marketer/finance/withdrawals` | POST | Request withdrawal |
| `/api/marketer/commission-rules` | GET | Commission rules |
| `/api/marketer/reports` | GET | Performance report |
| `/api/marketer/onboarding/job-type` | POST | Set job type (onboarding) |
| `/api/marketer/onboarding/profile` | POST | Complete profile (onboarding) |
| `/api/marketer/onboarding/complete` | POST | Finish onboarding |