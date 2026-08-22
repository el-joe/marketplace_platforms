<table class="table-base w-full">
    <thead>
        <tr>
            <th>{{ __('admin.admin_listings.customer_col') }}</th>
            <th>{{ __('admin.rating') }}</th>
            <th>{{ __('admin.admin_listings.review_col') }}</th>
            <th>{{ __('admin.status') }}</th>
            <th>{{ __('admin.date') }}</th>
            <th class="text-end">{{ __('admin.actions') }}</th>
        </tr>
    </thead>
    <tbody>
        @forelse($reviews as $review)
            <tr class="border-b border-gray-100" id="review-row-{{ $review->id }}">
                <td>{{ $review->customer?->name ?? __('admin.admin_listings.anonymous') }}</td>
                <td class="whitespace-nowrap">
                    <span class="text-yellow-500">{{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}</span>
                </td>
                <td class="max-w-xs truncate" title="{{ $review->body }}">{{ \Illuminate\Support\Str::limit($review->body, 80) }}</td>
                <td>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700" data-review-status="{{ $review->id }}">
                        {{ ucfirst(str_replace('_', ' ', $review->status->value)) }}
                    </span>
                </td>
                <td>{{ $review->created_at->format('Y-m-d') }}</td>
                <td class="text-end whitespace-nowrap">
                    <a href="{{ route('admin.reviews.show', $review) }}" class="text-primary-600 hover:underline text-xs" target="_blank">{{ __('admin.view') }}</a>
                    <button type="button" @click="approveReview('{{ $review->id }}')" class="text-green-600 hover:underline text-xs ms-2">{{ __('admin.approve') }}</button>
                    <button type="button" @click="rejectReview('{{ $review->id }}')" class="text-red-600 hover:underline text-xs ms-2">{{ __('admin.reject') }}</button>
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="text-center text-gray-400 py-6">{{ __('admin.admin_listings.no_reviews_yet') }}</td></tr>
        @endforelse
    </tbody>
</table>
@if($reviews->hasPages())
    <div class="px-4 py-3 border-t border-gray-100">
        {{ $reviews->links() }}
    </div>
@endif
