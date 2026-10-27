{{-- Card carousel returned by storefront.product.similar. --}}
<x-theme::carousel slide-width="[&>*]:w-[44%] sm:[&>*]:w-[30%] lg:[&>*]:w-[22%] xl:[&>*]:w-[18%]">
    @foreach ($cards as $card)
        <div class="flex">
            <x-theme::product-card :product="$card" class="w-full" />
        </div>
    @endforeach
</x-theme::carousel>
