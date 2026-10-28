<?php

return [
    'title' => 'Cart',
    'buy_now_title' => 'Buy Now',
    'heading_count' => '{0} Your cart is empty|{1} 1 item in your cart|[2,*] :count items in your cart',
    'buy_now_note' => 'Only this item is in your Buy Now order. Your cart is unchanged.',
    'back_to_cart' => 'Back to cart',
    'continue_shopping' => 'Continue shopping',

    'empty' => [
        'title' => 'Your cart is empty',
        'text' => 'Find something you love and add it here.',
        'buy_now_title' => 'Nothing to buy right now',
        'buy_now_text' => 'Your Buy Now item expired or was already ordered. Open the product again to buy it.',
        'recently_viewed' => 'Recently viewed',
    ],

    'group' => [
        'sold_by' => 'Sold by :store',
        'package' => '{1} 1 item · ships as one package|[2,*] :count items · ship as one package',
        'delivery_by' => 'Delivery by :date',
        'add_pincode' => 'Add a delivery pincode to see the delivery date',
    ],

    'line' => [
        'quantity' => 'Qty',
        'update' => 'Update',
        'remove' => 'Remove',
        'save_for_later' => 'Save for later',
        'move_to_cart' => 'Move to cart',
        'move_to_wishlist' => 'Move to wishlist',
        'unavailable_product' => 'Product no longer available',
        'line_total' => 'Item total',
    ],

    'sections' => [
        'unavailable' => 'Not available',
        'unavailable_text' => 'These items can’t be ordered right now. Remove them or save them for later to continue.',
        'saved' => 'Saved for later (:count)',
    ],

    'issues' => [
        'price_up' => 'Price went up from :old to :new.',
        'price_down' => 'Good news! Price dropped from :old to :new.',
        'low_stock' => '{1} Only 1 left, so we changed the quantity to 1.|[2,*] Only :count left, so we changed the quantity to :count.',
        'quantity_capped' => 'You can buy up to :max of this item, so we changed the quantity.',
        'out_of_stock' => 'Out of stock',
        'unavailable' => 'No longer available',
    ],

    'summary' => [
        'title' => 'Price details',
        'items' => '{1} Price (1 item)|[2,*] Price (:count items)',
        'total_mrp' => 'Total MRP',
        'discount' => 'Discount on MRP',
        'subtotal' => 'Subtotal',
        'delivery' => 'Delivery',
        'cod_fee' => 'Cash on Delivery fee',
        'coupon' => 'Coupon discount',
        'free' => 'FREE',
        'order_total' => 'Order Total',
        'you_save' => 'You will save :amount on this order',
        'continue' => 'Continue',
        'checkout' => 'Checkout',
        'checkout_soon' => 'Checkout opens soon.',
        'resolve_issues' => 'Remove or save the unavailable items to continue.',
        'secure' => 'Safe and secure payments. Easy returns.',
    ],

    'pincode' => [
        'label' => 'Delivery pincode',
        'placeholder' => 'Enter pincode',
        'check' => 'Check',
        'change' => 'Change',
        'deliver_to' => 'Deliver to :pincode',
    ],

    'mini' => [
        'title' => 'Added to cart',
        'heading' => 'Your cart',
        'subtotal' => 'Subtotal',
        'view_cart' => 'View cart',
        'checkout' => 'Checkout',
        'more' => '{1} + 1 more item|[2,*] + :count more items',
        'empty' => 'Your cart is empty.',
        'loading' => 'Loading your cart…',
    ],

    'messages' => [
        'added' => 'Added to cart',
        'added_capped' => 'Added to cart. Quantity limited to :count.',
        'updated' => 'Quantity updated',
        'removed' => 'Removed from cart',
        'saved_for_later' => 'Saved for later',
        'moved_to_cart' => 'Moved to cart',
        'moved_to_wishlist' => 'Moved to wishlist',
        'pincode_saved' => 'Showing delivery dates for :pincode',
    ],

    'errors' => [
        'unavailable' => 'Sorry, this item is no longer available.',
        'out_of_stock' => 'Sorry, this item is out of stock.',
        'cart_full' => 'Your cart can hold up to :max different items. Remove something to add more.',
        'quantity' => 'Choose a quantity between 1 and :max.',
        'choose_size' => 'Please choose a size first.',
        'choose_variant' => 'Choose a size or colour to add this item to your cart.',
        'saved_line' => 'Move this item to your cart first.',
        'not_saved' => 'This item is already in your cart.',
        'login_required' => 'Please log in to continue.',
        'pincode' => 'Enter a valid 6-digit pincode.',
    ],
];
