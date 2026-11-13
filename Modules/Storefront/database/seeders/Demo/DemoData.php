<?php

declare(strict_types=1);

namespace Modules\Storefront\Database\Seeders\Demo;

/**
 * Static data of the client demo (MarketplaceDemoSeeder): real pincodes, suppliers, brands,
 * products with their photo sources, customers and review texts. Names, brands and copy are
 * original; photos come from two free sources (see PHOTOS) and are for demos only.
 */
final class DemoData
{
    public const PASSWORD = 'Secret#123';

    /**
     * Photo sources: key => [urls, credit]. "dj*" are studio product renders from DummyJSON
     * (a free prototyping API, cdn.dummyjson.com); "ov:*" are public-domain (PDM) or CC0
     * photos found through Openverse (api.openverse.org), credited to their pages.
     *
     * @var array<string, array{0: list<string>, 1: string}>
     */
    public const PHOTOS = [
        'dj3' => [['https://cdn.dummyjson.com/product-images/beauty/powder-canister/1.webp'], 'DummyJSON'],
        'dj4' => [['https://cdn.dummyjson.com/product-images/beauty/red-lipstick/1.webp'], 'DummyJSON'],
        'dj5' => [['https://cdn.dummyjson.com/product-images/beauty/red-nail-polish/1.webp'], 'DummyJSON'],
        'dj44' => [['https://cdn.dummyjson.com/product-images/home-decoration/family-tree-photo-frame/1.webp'], 'DummyJSON'],
        'dj45' => [['https://cdn.dummyjson.com/product-images/home-decoration/house-showpiece-plant/1.webp', 'https://cdn.dummyjson.com/product-images/home-decoration/house-showpiece-plant/2.webp', 'https://cdn.dummyjson.com/product-images/home-decoration/house-showpiece-plant/3.webp'], 'DummyJSON'],
        'dj46' => [['https://cdn.dummyjson.com/product-images/home-decoration/plant-pot/1.webp', 'https://cdn.dummyjson.com/product-images/home-decoration/plant-pot/2.webp', 'https://cdn.dummyjson.com/product-images/home-decoration/plant-pot/3.webp', 'https://cdn.dummyjson.com/product-images/home-decoration/plant-pot/4.webp'], 'DummyJSON'],
        'dj47' => [['https://cdn.dummyjson.com/product-images/home-decoration/table-lamp/1.webp'], 'DummyJSON'],
        'dj48' => [['https://cdn.dummyjson.com/product-images/kitchen-accessories/bamboo-spatula/1.webp'], 'DummyJSON'],
        'dj51' => [['https://cdn.dummyjson.com/product-images/kitchen-accessories/boxed-blender/1.webp', 'https://cdn.dummyjson.com/product-images/kitchen-accessories/boxed-blender/2.webp', 'https://cdn.dummyjson.com/product-images/kitchen-accessories/boxed-blender/3.webp', 'https://cdn.dummyjson.com/product-images/kitchen-accessories/boxed-blender/4.webp'], 'DummyJSON'],
        'dj52' => [['https://cdn.dummyjson.com/product-images/kitchen-accessories/carbon-steel-wok/1.webp'], 'DummyJSON'],
        'dj53' => [['https://cdn.dummyjson.com/product-images/kitchen-accessories/chopping-board/1.webp'], 'DummyJSON'],
        'dj56' => [['https://cdn.dummyjson.com/product-images/kitchen-accessories/electric-stove/1.webp', 'https://cdn.dummyjson.com/product-images/kitchen-accessories/electric-stove/2.webp', 'https://cdn.dummyjson.com/product-images/kitchen-accessories/electric-stove/3.webp', 'https://cdn.dummyjson.com/product-images/kitchen-accessories/electric-stove/4.webp'], 'DummyJSON'],
        'dj61' => [['https://cdn.dummyjson.com/product-images/kitchen-accessories/hand-blender/1.webp'], 'DummyJSON'],
        'dj64' => [['https://cdn.dummyjson.com/product-images/kitchen-accessories/knife/1.webp'], 'DummyJSON'],
        'dj65' => [['https://cdn.dummyjson.com/product-images/kitchen-accessories/lunch-box/1.webp'], 'DummyJSON'],
        'dj66' => [['https://cdn.dummyjson.com/product-images/kitchen-accessories/microwave-oven/1.webp', 'https://cdn.dummyjson.com/product-images/kitchen-accessories/microwave-oven/2.webp', 'https://cdn.dummyjson.com/product-images/kitchen-accessories/microwave-oven/3.webp', 'https://cdn.dummyjson.com/product-images/kitchen-accessories/microwave-oven/4.webp'], 'DummyJSON'],
        'dj67' => [['https://cdn.dummyjson.com/product-images/kitchen-accessories/mug-tree-stand/1.webp', 'https://cdn.dummyjson.com/product-images/kitchen-accessories/mug-tree-stand/2.webp'], 'DummyJSON'],
        'dj68' => [['https://cdn.dummyjson.com/product-images/kitchen-accessories/pan/1.webp'], 'DummyJSON'],
        'dj71' => [['https://cdn.dummyjson.com/product-images/kitchen-accessories/silver-pot-with-glass-cap/1.webp'], 'DummyJSON'],
        'dj73' => [['https://cdn.dummyjson.com/product-images/kitchen-accessories/spice-rack/1.webp'], 'DummyJSON'],
        'dj75' => [['https://cdn.dummyjson.com/product-images/kitchen-accessories/tray/1.webp'], 'DummyJSON'],
        'dj76' => [['https://cdn.dummyjson.com/product-images/kitchen-accessories/wooden-rolling-pin/1.webp'], 'DummyJSON'],
        'dj83' => [['https://cdn.dummyjson.com/product-images/mens-shirts/blue-&-black-check-shirt/1.webp', 'https://cdn.dummyjson.com/product-images/mens-shirts/blue-&-black-check-shirt/2.webp', 'https://cdn.dummyjson.com/product-images/mens-shirts/blue-&-black-check-shirt/3.webp', 'https://cdn.dummyjson.com/product-images/mens-shirts/blue-&-black-check-shirt/4.webp'], 'DummyJSON'],
        'dj85' => [['https://cdn.dummyjson.com/product-images/mens-shirts/man-plaid-shirt/1.webp', 'https://cdn.dummyjson.com/product-images/mens-shirts/man-plaid-shirt/2.webp', 'https://cdn.dummyjson.com/product-images/mens-shirts/man-plaid-shirt/3.webp', 'https://cdn.dummyjson.com/product-images/mens-shirts/man-plaid-shirt/4.webp'], 'DummyJSON'],
        'dj86' => [['https://cdn.dummyjson.com/product-images/mens-shirts/man-short-sleeve-shirt/1.webp', 'https://cdn.dummyjson.com/product-images/mens-shirts/man-short-sleeve-shirt/2.webp', 'https://cdn.dummyjson.com/product-images/mens-shirts/man-short-sleeve-shirt/3.webp', 'https://cdn.dummyjson.com/product-images/mens-shirts/man-short-sleeve-shirt/4.webp'], 'DummyJSON'],
        'dj87' => [['https://cdn.dummyjson.com/product-images/mens-shirts/men-check-shirt/1.webp', 'https://cdn.dummyjson.com/product-images/mens-shirts/men-check-shirt/2.webp', 'https://cdn.dummyjson.com/product-images/mens-shirts/men-check-shirt/3.webp', 'https://cdn.dummyjson.com/product-images/mens-shirts/men-check-shirt/4.webp'], 'DummyJSON'],
        'dj154' => [['https://cdn.dummyjson.com/product-images/sunglasses/black-sun-glasses/1.webp', 'https://cdn.dummyjson.com/product-images/sunglasses/black-sun-glasses/2.webp', 'https://cdn.dummyjson.com/product-images/sunglasses/black-sun-glasses/3.webp'], 'DummyJSON'],
        'dj155' => [['https://cdn.dummyjson.com/product-images/sunglasses/classic-sun-glasses/1.webp', 'https://cdn.dummyjson.com/product-images/sunglasses/classic-sun-glasses/2.webp', 'https://cdn.dummyjson.com/product-images/sunglasses/classic-sun-glasses/3.webp'], 'DummyJSON'],
        'dj156' => [['https://cdn.dummyjson.com/product-images/sunglasses/green-and-black-glasses/1.webp', 'https://cdn.dummyjson.com/product-images/sunglasses/green-and-black-glasses/2.webp', 'https://cdn.dummyjson.com/product-images/sunglasses/green-and-black-glasses/3.webp'], 'DummyJSON'],
        'dj162' => [['https://cdn.dummyjson.com/product-images/tops/blue-frock/1.webp', 'https://cdn.dummyjson.com/product-images/tops/blue-frock/2.webp', 'https://cdn.dummyjson.com/product-images/tops/blue-frock/3.webp', 'https://cdn.dummyjson.com/product-images/tops/blue-frock/4.webp'], 'DummyJSON'],
        'dj163' => [['https://cdn.dummyjson.com/product-images/tops/girl-summer-dress/1.webp', 'https://cdn.dummyjson.com/product-images/tops/girl-summer-dress/2.webp', 'https://cdn.dummyjson.com/product-images/tops/girl-summer-dress/3.webp', 'https://cdn.dummyjson.com/product-images/tops/girl-summer-dress/4.webp'], 'DummyJSON'],
        'dj164' => [['https://cdn.dummyjson.com/product-images/tops/gray-dress/1.webp', 'https://cdn.dummyjson.com/product-images/tops/gray-dress/2.webp', 'https://cdn.dummyjson.com/product-images/tops/gray-dress/3.webp', 'https://cdn.dummyjson.com/product-images/tops/gray-dress/4.webp'], 'DummyJSON'],
        'dj165' => [['https://cdn.dummyjson.com/product-images/tops/short-frock/1.webp', 'https://cdn.dummyjson.com/product-images/tops/short-frock/2.webp', 'https://cdn.dummyjson.com/product-images/tops/short-frock/3.webp', 'https://cdn.dummyjson.com/product-images/tops/short-frock/4.webp'], 'DummyJSON'],
        'dj166' => [['https://cdn.dummyjson.com/product-images/tops/tartan-dress/1.webp', 'https://cdn.dummyjson.com/product-images/tops/tartan-dress/2.webp', 'https://cdn.dummyjson.com/product-images/tops/tartan-dress/3.webp', 'https://cdn.dummyjson.com/product-images/tops/tartan-dress/4.webp'], 'DummyJSON'],
        'dj172' => [["https://cdn.dummyjson.com/product-images/womens-bags/blue-women's-handbag/1.webp", "https://cdn.dummyjson.com/product-images/womens-bags/blue-women's-handbag/2.webp", "https://cdn.dummyjson.com/product-images/womens-bags/blue-women's-handbag/3.webp"], 'DummyJSON'],
        'dj175' => [['https://cdn.dummyjson.com/product-images/womens-bags/white-faux-leather-backpack/1.webp', 'https://cdn.dummyjson.com/product-images/womens-bags/white-faux-leather-backpack/2.webp', 'https://cdn.dummyjson.com/product-images/womens-bags/white-faux-leather-backpack/3.webp'], 'DummyJSON'],
        'dj176' => [['https://cdn.dummyjson.com/product-images/womens-bags/women-handbag-black/1.webp', 'https://cdn.dummyjson.com/product-images/womens-bags/women-handbag-black/2.webp', 'https://cdn.dummyjson.com/product-images/womens-bags/women-handbag-black/3.webp'], 'DummyJSON'],
        'dj177' => [["https://cdn.dummyjson.com/product-images/womens-dresses/black-women's-gown/1.webp", "https://cdn.dummyjson.com/product-images/womens-dresses/black-women's-gown/2.webp", "https://cdn.dummyjson.com/product-images/womens-dresses/black-women's-gown/3.webp", "https://cdn.dummyjson.com/product-images/womens-dresses/black-women's-gown/4.webp"], 'DummyJSON'],
        'dj180' => [['https://cdn.dummyjson.com/product-images/womens-dresses/dress-pea/1.webp', 'https://cdn.dummyjson.com/product-images/womens-dresses/dress-pea/2.webp', 'https://cdn.dummyjson.com/product-images/womens-dresses/dress-pea/3.webp', 'https://cdn.dummyjson.com/product-images/womens-dresses/dress-pea/4.webp'], 'DummyJSON'],
        'dj182' => [['https://cdn.dummyjson.com/product-images/womens-jewellery/green-crystal-earring/1.webp', 'https://cdn.dummyjson.com/product-images/womens-jewellery/green-crystal-earring/2.webp', 'https://cdn.dummyjson.com/product-images/womens-jewellery/green-crystal-earring/3.webp'], 'DummyJSON'],
        'dj183' => [['https://cdn.dummyjson.com/product-images/womens-jewellery/green-oval-earring/1.webp', 'https://cdn.dummyjson.com/product-images/womens-jewellery/green-oval-earring/2.webp', 'https://cdn.dummyjson.com/product-images/womens-jewellery/green-oval-earring/3.webp'], 'DummyJSON'],
        'dj184' => [['https://cdn.dummyjson.com/product-images/womens-jewellery/tropical-earring/1.webp', 'https://cdn.dummyjson.com/product-images/womens-jewellery/tropical-earring/2.webp', 'https://cdn.dummyjson.com/product-images/womens-jewellery/tropical-earring/3.webp'], 'DummyJSON'],
        'dj188' => [['https://cdn.dummyjson.com/product-images/womens-shoes/pampi-shoes/1.webp', 'https://cdn.dummyjson.com/product-images/womens-shoes/pampi-shoes/2.webp', 'https://cdn.dummyjson.com/product-images/womens-shoes/pampi-shoes/3.webp', 'https://cdn.dummyjson.com/product-images/womens-shoes/pampi-shoes/4.webp'], 'DummyJSON'],
        'dj189' => [['https://cdn.dummyjson.com/product-images/womens-shoes/red-shoes/1.webp', 'https://cdn.dummyjson.com/product-images/womens-shoes/red-shoes/2.webp', 'https://cdn.dummyjson.com/product-images/womens-shoes/red-shoes/3.webp', 'https://cdn.dummyjson.com/product-images/womens-shoes/red-shoes/4.webp'], 'DummyJSON'],
        'dj193' => [['https://cdn.dummyjson.com/product-images/womens-watches/watch-gold-for-women/1.webp', 'https://cdn.dummyjson.com/product-images/womens-watches/watch-gold-for-women/2.webp', 'https://cdn.dummyjson.com/product-images/womens-watches/watch-gold-for-women/3.webp'], 'DummyJSON'],
        'dj194' => [["https://cdn.dummyjson.com/product-images/womens-watches/women's-wrist-watch/1.webp", "https://cdn.dummyjson.com/product-images/womens-watches/women's-wrist-watch/2.webp", "https://cdn.dummyjson.com/product-images/womens-watches/women's-wrist-watch/3.webp"], 'DummyJSON'],

        'ov:saree-pink-print' => [['https://live.staticflickr.com/1887/44101092642_609bb842c9_b.jpg'], 'PDM, flickr.com/photos/166386235@N07/44101092642'],
        'ov:saree-pink-check' => [['https://live.staticflickr.com/1863/43430827214_5b3ee5dc35_b.jpg'], 'PDM, flickr.com/photos/166386235@N07/43430827214'],
        'ov:saree-colour-block' => [['https://live.staticflickr.com/1855/42340753970_d63357db14_b.jpg'], 'PDM, flickr.com/photos/166386235@N07/42340753970'],
        'ov:saree-grey-motif' => [['https://live.staticflickr.com/1862/43430856134_f416cbed7a_b.jpg'], 'PDM, flickr.com/photos/166386235@N07/43430856134'],
        'ov:saree-pink-kalamkari' => [['https://live.staticflickr.com/1832/43430824474_ab4271f2a3_b.jpg'], 'PDM, flickr.com/photos/166386235@N07/43430824474'],
        'ov:saree-orange-stripe' => [['https://live.staticflickr.com/1849/42340748770_ced8285dba_b.jpg'], 'PDM, flickr.com/photos/166386235@N07/42340748770'],
        'ov:saree-black-shibori' => [['https://live.staticflickr.com/1883/43242961895_cf7d83acfb_b.jpg'], 'PDM, flickr.com/photos/166386235@N07/43242961895'],
        'ov:saree-red-check' => [['https://live.staticflickr.com/1881/43242981575_cbfb31970e_b.jpg'], 'PDM, flickr.com/photos/166386235@N07/43242981575'],
        'ov:lehenga-blue-brocade' => [['https://live.staticflickr.com/5593/31015877920_5e060995f5_b.jpg'], 'PDM, flickr.com/photos/148732679@N08/31015877920'],
        'ov:lehenga-orange-print' => [['https://live.staticflickr.com/5347/31015766870_2ceaee722e_b.jpg'], 'PDM, flickr.com/photos/148732679@N08/31015766870'],
        'ov:lehenga-black-jacket' => [['https://live.staticflickr.com/5568/31240943862_f365e099c2_b.jpg'], 'PDM, flickr.com/photos/148732679@N08/31240943862'],
        'ov:lehenga-green-print' => [['https://live.staticflickr.com/5449/31348730066_3063c17516_b.jpg'], 'PDM, flickr.com/photos/148732679@N08/31348730066'],
        'ov:lehenga-ombre' => [['https://live.staticflickr.com/421/31248632380_b00a310a90_b.jpg', 'https://live.staticflickr.com/309/30811170213_812be16dc2_b.jpg'], 'PDM, flickr.com/photos/148732679@N08'],
        'ov:lehenga-bridal-print' => [['https://live.staticflickr.com/370/31248630240_961ed362f4_b.jpg', 'https://live.staticflickr.com/5578/31248639320_6b6908481e_b.jpg', 'https://live.staticflickr.com/569/31621164285_73484fd5eb_b.jpg'], 'PDM, flickr.com/photos/148732679@N08'],
        'ov:dupatta-pile' => [['https://pd.w.org/2026/07/3196a46b9879476c8.51509682-1536x2048.jpeg'], 'CC0, wordpress.org/photos/photo/3196a46b98'],
        'ov:jhumka' => [['https://images.rawpixel.com/editor_1024/cHJpdmF0ZS9sci9pbWFnZXMvd2Vic2l0ZS8yMDIyLTEyL2FpYzE5MDg1Ni1pbWFnZS5qcGc.jpg'], 'CC0, rawpixel.com/image/8944914'],
        'ov:necklace-red' => [['https://live.staticflickr.com/65535/54625622455_49b2a64f4d_b.jpg'], 'CC0, flickr.com/photos/203097426@N06/54625622455'],
        'ov:crop-top' => [['https://live.staticflickr.com/65535/48648320081_39b86aeb38_b.jpg', 'https://live.staticflickr.com/65535/48648344281_73264a2bd5_b.jpg'], 'CC0/PDM, flickr.com/photos/183925915@N04'],
        'ov:tees-black' => [['https://live.staticflickr.com/65535/49060458101_ea9eef243b.jpg'], 'CC0, flickr.com/photos/185514373@N06/49060458101'],
        'ov:bedsheet-floral' => [['https://live.staticflickr.com/65535/53865956493_ef4798ac5a_b.jpg'], 'CC0, flickr.com/photos/200781279@N05/53865956493'],
        'ov:bedsheet-black' => [['https://live.staticflickr.com/65535/49062754297_bee0cbbae4.jpg'], 'CC0, flickr.com/photos/185514373@N06/49062754297'],
        'ov:curtains' => [['https://live.staticflickr.com/3418/3966767700_93837a2409_b.jpg'], 'CC0, flickr.com/photos/49976053@N00/3966767700'],
        'ov:earbuds-case' => [['https://live.staticflickr.com/65535/52063601444_87d8b1d840.jpg'], 'PDM, flickr.com/photos/195657162@N08/52063601444'],
        'ov:earphones-wired' => [['https://live.staticflickr.com/65535/49062392917_c76f41aef8.jpg'], 'CC0, flickr.com/photos/185514373@N06/49062392917'],
        'ov:speaker' => [['https://images.rawpixel.com/editor_1024/cHJpdmF0ZS9sci9pbWFnZXMvd2Vic2l0ZS8yMDIzLTA0L2JzMzY1LWltYWdlLmpwZw.jpg', 'https://images.rawpixel.com/editor_1024/cHJpdmF0ZS9sci9pbWFnZXMvd2Vic2l0ZS8yMDIzLTA0L2JzMzYwLWltYWdlLmpwZw.jpg', 'https://images.rawpixel.com/editor_1024/cHJpdmF0ZS9sci9pbWFnZXMvd2Vic2l0ZS8yMDIzLTA0L2JzMzU4LWltYWdlLmpwZw.jpg'], 'CC0, rawpixel.com/image/11524277'],
    ];

    /**
     * India Post format (officename, pincode, district, statename, latitude, longitude) for
     * directory:import-pincodes. The zone column is set afterwards (metro / ne_jk).
     *
     * @var list<array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string, 6: string}>
     */
    public const PINCODES = [
        ['Bengaluru GPO', '560001', 'Bengaluru Urban', 'Karnataka', '12.9762', '77.6033', 'metro'],
        ['Koramangala SO', '560034', 'Bengaluru Urban', 'Karnataka', '12.9352', '77.6245', 'metro'],
        ['Mysuru HO', '570001', 'Mysuru', 'Karnataka', '12.2958', '76.6394', 'roi'],
        ['New Delhi GPO', '110001', 'New Delhi', 'Delhi', '28.6328', '77.2197', 'metro'],
        ['Gurugram HO', '122001', 'Gurugram', 'Haryana', '28.4595', '77.0266', 'metro'],
        ['Noida Sector 1 SO', '201301', 'Gautam Buddha Nagar', 'Uttar Pradesh', '28.5355', '77.3910', 'metro'],
        ['Mumbai GPO', '400001', 'Mumbai', 'Maharashtra', '18.9388', '72.8354', 'metro'],
        ['Thane HO', '400601', 'Thane', 'Maharashtra', '19.2183', '72.9781', 'metro'],
        ['Pune City HO', '411001', 'Pune', 'Maharashtra', '18.5204', '73.8567', 'metro'],
        ['Nagpur GPO', '440001', 'Nagpur', 'Maharashtra', '21.1458', '79.0882', 'roi'],
        ['Chennai GPO', '600001', 'Chennai', 'Tamil Nadu', '13.0878', '80.2785', 'metro'],
        ['Coimbatore HO', '641001', 'Coimbatore', 'Tamil Nadu', '11.0168', '76.9558', 'roi'],
        ['Kolkata GPO', '700001', 'Kolkata', 'West Bengal', '22.5726', '88.3639', 'metro'],
        ['Hyderabad GPO', '500001', 'Hyderabad', 'Telangana', '17.3850', '78.4867', 'metro'],
        ['Visakhapatnam HO', '530001', 'Visakhapatnam', 'Andhra Pradesh', '17.6868', '83.2185', 'roi'],
        ['Ahmedabad GPO', '380001', 'Ahmedabad', 'Gujarat', '23.0225', '72.5714', 'metro'],
        ['Surat HO', '395003', 'Surat', 'Gujarat', '21.1702', '72.8311', 'roi'],
        ['Jaipur GPO', '302001', 'Jaipur', 'Rajasthan', '26.9124', '75.7873', 'roi'],
        ['Lucknow GPO', '226001', 'Lucknow', 'Uttar Pradesh', '26.8467', '80.9462', 'roi'],
        ['Kanpur HO', '208001', 'Kanpur Nagar', 'Uttar Pradesh', '26.4499', '80.3319', 'roi'],
        ['Varanasi HO', '221001', 'Varanasi', 'Uttar Pradesh', '25.3176', '82.9739', 'roi'],
        ['Indore GPO', '452001', 'Indore', 'Madhya Pradesh', '22.7196', '75.8577', 'roi'],
        ['Bhopal GPO', '462001', 'Bhopal', 'Madhya Pradesh', '23.2599', '77.4126', 'roi'],
        ['Ernakulam HO', '682011', 'Ernakulam', 'Kerala', '9.9816', '76.2999', 'roi'],
        ['Patna GPO', '800001', 'Patna', 'Bihar', '25.5941', '85.1376', 'roi'],
        ['Bhubaneswar GPO', '751001', 'Khordha', 'Odisha', '20.2961', '85.8245', 'roi'],
        ['Chandigarh Sector 17 HO', '160017', 'Chandigarh', 'Chandigarh', '30.7333', '76.7794', 'roi'],
        ['Ludhiana HO', '141001', 'Ludhiana', 'Punjab', '30.9010', '75.8573', 'roi'],
        ['Dehradun HO', '248001', 'Dehradun', 'Uttarakhand', '30.3165', '78.0322', 'roi'],
        ['Panaji HO', '403001', 'North Goa', 'Goa', '15.4909', '73.8278', 'roi'],
        ['Guwahati GPO', '781001', 'Kamrup Metropolitan', 'Assam', '26.1445', '91.7362', 'ne_jk'],
        ['Jammu Tawi HO', '180001', 'Jammu', 'Jammu and Kashmir', '32.7266', '74.8570', 'ne_jk'],
    ];

    /**
     * Suppliers added to the three of DemoCatalogSeeder: [code, store name, owner email,
     * warehouse pincode, city, state, owner name].
     *
     * @var list<array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string, 6: string}>
     */
    public const SELLERS = [
        ['DEMO04', 'RasoiCraft Kitchenware', 'demo.seller4@example.com', '302001', 'Jaipur', 'Rajasthan', 'Mahesh Agarwal'],
        ['DEMO05', 'Aanya Fashion Studio', 'demo.seller5@example.com', '395003', 'Surat', 'Gujarat', 'Aanya Patel'],
        ['DEMO06', 'Noor Accessories', 'demo.seller6@example.com', '500001', 'Hyderabad', 'Telangana', 'Farah Siddiqui'],
    ];

    /**
     * The warehouses of DemoCatalogSeeder's suppliers get a real city/state: code => [city, state].
     *
     * @var array<string, array{0: string, 1: string}>
     */
    public const EXISTING_SELLER_CITIES = [
        'DEMO01' => ['Bengaluru', 'Karnataka'],
        'DEMO02' => ['New Delhi', 'Delhi'],
        'DEMO03' => ['Mumbai', 'Maharashtra'],
    ];

    /**
     * Original brand names: slug => [name, verified].
     *
     * @var array<string, array{0: string, 1: bool}>
     */
    public const BRANDS = [
        'saanjh' => ['Saanjh', true],
        'urban-thread' => ['Urban Thread', true],
        'aanya' => ['Aanya', false],
        'rasoi-craft' => ['Rasoi Craft', true],
        'kosha-living' => ['Kosha Living', false],
        'taal-audio' => ['Taal Audio', true],
        'noor' => ['Noor', false],
        'glow-ritual' => ['Glow Ritual', false],
    ];

    /**
     * The 30 products of DemoCatalogSeeder (by seller SKU): real photos (and a fitting name /
     * brand), or "pause" while no free photo of the item exists.
     *
     * @var array<string, array{photos?: list<string>, name?: string, brand?: string, pause?: bool}>
     */
    public const EXISTING = [
        'DEMO-001' => ['photos' => ['ov:saree-red-check'], 'name' => 'Art Silk Saree, Red & Black Checks', 'brand' => 'saanjh'],
        'DEMO-002' => ['photos' => ['ov:saree-grey-motif'], 'name' => 'Handloom Cotton Saree, Block-Print Motifs', 'brand' => 'saanjh'],
        'DEMO-003' => ['photos' => ['ov:saree-pink-kalamkari', 'ov:saree-pink-print'], 'name' => 'Printed Cotton Saree with Kalamkari Pallu', 'brand' => 'saanjh'],
        'DEMO-004' => ['photos' => ['ov:saree-black-shibori'], 'name' => 'Shibori Dyed Cotton Saree, Black & White', 'brand' => 'saanjh'],
        'DEMO-005' => ['pause' => true],
        'DEMO-006' => ['pause' => true],
        'DEMO-007' => ['pause' => true],
        'DEMO-008' => ['photos' => ['ov:lehenga-bridal-print'], 'name' => 'Printed Silk Lehenga Choli with Mirror Work', 'brand' => 'saanjh'],
        'DEMO-009' => ['photos' => ['ov:dupatta-pile'], 'name' => 'Pure Cotton Dupatta, Assorted Colours', 'brand' => 'saanjh'],
        'DEMO-010' => ['photos' => ['ov:crop-top'], 'name' => 'Printed Knit Crop Top, Scoop Neck', 'brand' => 'urban-thread'],
        'DEMO-011' => ['photos' => ['dj177'], 'name' => 'Floral Print Off-Shoulder Maxi Gown', 'brand' => 'urban-thread'],
        'DEMO-012' => ['pause' => true],
        'DEMO-013' => ['pause' => true],
        'DEMO-014' => ['pause' => true],
        'DEMO-015' => ['photos' => ['ov:tees-black'], 'name' => 'Men Cotton Crew Neck T-Shirt, Black (Pack of 2)', 'brand' => 'urban-thread'],
        'DEMO-016' => ['photos' => ['dj87'], 'name' => 'Men Linen Blend Check Shirt, Green', 'brand' => 'urban-thread'],
        'DEMO-017' => ['pause' => true],
        'DEMO-018' => ['pause' => true],
        'DEMO-019' => ['photos' => ['ov:bedsheet-floral'], 'name' => 'Cotton Double Bedsheet, Floral Jaal Print', 'brand' => 'kosha-living'],
        'DEMO-020' => ['photos' => ['ov:curtains'], 'name' => 'Room Darkening Window Curtains, Grey (Set of 2)', 'brand' => 'kosha-living'],
        'DEMO-021' => ['photos' => ['dj68'], 'name' => 'Non-Stick Fry Pan, 26 cm', 'brand' => 'rasoi-craft'],
        'DEMO-022' => ['photos' => ['dj65'], 'name' => 'Stainless Steel Tiffin, 4 Tier', 'brand' => 'rasoi-craft'],
        'DEMO-023' => ['photos' => ['dj44'], 'name' => 'Family Tree Photo Frame Wall Decor', 'brand' => 'kosha-living'],
        'DEMO-024' => ['photos' => ['ov:earbuds-case'], 'name' => 'True Wireless Earbuds with Charging Case', 'brand' => 'taal-audio'],
        'DEMO-025' => ['photos' => ['ov:speaker'], 'name' => 'Portable Bluetooth Speaker, 10 W', 'brand' => 'taal-audio'],
        'DEMO-026' => ['pause' => true],
        'DEMO-027' => ['pause' => true],
        'DEMO-028' => ['photos' => ['ov:jhumka'], 'name' => 'Kundan Jhumka Earrings with Pearl Drops', 'brand' => 'noor'],
        'DEMO-029' => ['photos' => ['ov:necklace-red'], 'name' => 'Ruby Stone Statement Necklace', 'brand' => 'noor'],
        'DEMO-030' => ['photos' => ['dj176'], 'name' => 'Structured Handbag with Sling Strap, Black', 'brand' => 'aanya'],
    ];

    /**
     * New listings, created through ProductFactoryService and approved like a seller listing:
     * [key, seller code, brand, leaf category slug, name, price, MRP, colour, sizes (axis options,
     * [] = none), stock, photos, highlights, description, extra attributes (code => option)].
     *
     * @var list<array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string, 6: string, 7: string, 8: list<string>, 9: int, 10: list<string>, 11: list<string>, 12: string, 13: array<string, string>}>
     */
    public const PRODUCTS = [
        // Ethnic wear (Shree Ethnic House).
        ['saree-colour-block', 'DEMO01', 'saanjh', 'cotton-sarees', 'Colour Block Mulmul Cotton Saree', '899', '2199', 'Multicolour', [], 30, ['ov:saree-colour-block'], ['Soft mulmul cotton', 'Bold colour-block pallu', '5.5 m saree with 0.8 m blouse piece'], 'An easy-drape mulmul cotton saree with a modern colour-block pallu. Light enough for long summer days.', ['fabric' => 'Cotton', 'pattern' => 'Colourblocked', 'occasion' => 'Daily Wear']],
        ['saree-orange-stripe', 'DEMO01', 'saanjh', 'georgette-sarees', 'Striped Georgette Saree, Orange', '749', '1999', 'Orange', [], 24, ['ov:saree-orange-stripe'], ['Flowing georgette', 'Leheriya-style stripes', 'Unstitched blouse piece'], 'A festive orange georgette saree with diagonal stripes and a contrast border.', ['fabric' => 'Georgette', 'pattern' => 'Striped', 'occasion' => 'Festive']],
        ['saree-pink-check', 'DEMO01', 'saanjh', 'cotton-sarees', 'Handblock Cotton Saree, Pink & White Checks', '999', '2499', 'Pink', [], 18, ['ov:saree-pink-check'], ['Hand block printed', 'Pure cotton', 'Contrast check pallu'], 'Hand block printed by artisans in Bagru: a pink cotton saree with a white check pallu.', ['fabric' => 'Cotton', 'pattern' => 'Checked', 'occasion' => 'Casual']],
        ['lehenga-blue', 'DEMO01', 'saanjh', 'party-lehengas', 'Blue Brocade Lehenga with Red Choli', '2899', '6999', 'Blue', ['S', 'M', 'L'], 10, ['ov:lehenga-blue-brocade'], ['Brocade flared lehenga', 'Contrast red choli', 'Semi-stitched, fits up to 42 in'], 'A twirl-ready brocade lehenga in royal blue with a sleeveless red choli and woven border.', ['fabric' => 'Silk', 'pattern' => 'Woven Design', 'occasion' => 'Wedding']],
        ['lehenga-orange', 'DEMO01', 'saanjh', 'lehenga-cholis', 'Printed Lehenga with Black Lace Blouse', '2499', '5999', 'Orange', ['S', 'M', 'L'], 12, ['ov:lehenga-orange-print'], ['Digital print lehenga', 'Lace full-sleeve blouse', 'Matching dupatta'], 'Rust-orange printed lehenga paired with a black lace blouse: festive without being heavy.', ['fabric' => 'Satin', 'pattern' => 'Printed', 'occasion' => 'Festive']],
        ['lehenga-black', 'DEMO01', 'saanjh', 'lehenga-cholis', 'Black Lehenga with Peach Jacket Choli', '2699', '6499', 'Black', ['S', 'M', 'L'], 9, ['ov:lehenga-black-jacket'], ['Jacket-style choli', 'Embroidered hem', 'Can-can for flare'], 'A black A-line lehenga with a peach jacket choli and an embroidered hem band.', ['fabric' => 'Silk', 'pattern' => 'Embroidered', 'occasion' => 'Party']],
        ['lehenga-green', 'DEMO01', 'saanjh', 'party-lehengas', 'Green Printed Lehenga Choli Set', '2299', '5499', 'Green', ['S', 'M', 'L'], 14, ['ov:lehenga-green-print'], ['Ethnic motif print', 'Long-sleeve choli', 'Net dupatta'], 'Leaf-green lehenga with an ethnic motif print and a long-sleeve choli.', ['fabric' => 'Satin', 'pattern' => 'Ethnic Motifs', 'occasion' => 'Festive']],
        ['lehenga-ombre', 'DEMO01', 'saanjh', 'party-lehengas', 'Ombre Pink & Blue Printed Lehenga Set', '3199', '7499', 'Pink', ['S', 'M', 'L'], 8, ['ov:lehenga-ombre'], ['Ombre geometric print', 'Backless choli', 'Fully lined'], 'A statement ombre lehenga that fades from pink to blue, with a backless choli.', ['fabric' => 'Satin', 'pattern' => 'Geometric', 'occasion' => 'Party']],

        // Western wear, kids, bags, footwear (Aanya Fashion Studio).
        ['dress-leaf-maxi', 'DEMO05', 'aanya', 'maxi-dresses', 'Leaf Print Off-Shoulder Maxi Dress', '799', '1999', 'Black', ['S', 'M', 'L'], 30, ['dj163'], ['Breezy rayon', 'Off-shoulder neckline', 'Ankle length'], 'A monochrome leaf print maxi dress with an off-shoulder ruffle neckline.', ['fabric' => 'Rayon', 'pattern' => 'Printed', 'occasion' => 'Casual']],
        ['dress-grey-midi', 'DEMO05', 'aanya', 'midi-dresses', 'Button-Front Sleeveless Midi Dress, Grey', '699', '1699', 'Grey', ['S', 'M', 'L'], 26, ['dj164'], ['Linen blend', 'Front buttons and tie belt', 'Midi length'], 'An everyday grey midi dress with a button front and a self tie belt.', ['fabric' => 'Linen', 'pattern' => 'Solid', 'occasion' => 'Casual']],
        ['dress-mint-tiered', 'DEMO05', 'aanya', 'a-line-dresses', 'Tiered Ruffle Mini Dress with Waist Tie', '649', '1599', 'Mint', ['S', 'M', 'L'], 22, ['dj165'], ['Tiered ruffles', 'Strapless with waist tie', 'Lined'], 'A playful mint mini dress with three ruffle tiers and a bow at the waist.', ['fabric' => 'Polyester', 'pattern' => 'Solid', 'occasion' => 'Party']],
        ['dress-polka', 'DEMO05', 'aanya', 'a-line-dresses', 'Polka Dot Strapless Flared Dress', '899', '2199', 'White', ['S', 'M', 'L'], 16, ['dj180'], ['Fit-and-flare', 'Bow detail', 'Concealed zip'], 'A vintage-style polka dot dress with a fitted bodice and a full skirt.', ['fabric' => 'Cotton', 'pattern' => 'Printed', 'occasion' => 'Party']],
        ['frock-blue', 'DEMO05', 'aanya', 'frocks', 'Polka Dot Party Frock for Girls', '499', '1199', 'Blue', ['2-3 Years', '3-4 Years', '4-5 Years'], 28, ['dj162'], ['Soft cotton lining', 'Flutter sleeves', 'Back zip'], 'A cheerful blue party frock with oversized polka dots and flutter sleeves.', ['fabric' => 'Cotton', 'ideal-for' => 'Girls']],
        ['frock-tartan', 'DEMO05', 'aanya', 'frocks', 'Tartan Check Frock with Bow', '549', '1299', 'Grey', ['2-3 Years', '3-4 Years', '4-5 Years'], 20, ['dj166'], ['Tartan checks', 'Satin bow', 'Lined skirt'], 'A grey tartan frock with a black satin bow, made for birthdays and family functions.', ['fabric' => 'Cotton Blend', 'ideal-for' => 'Girls']],
        ['bag-navy-tote', 'DEMO05', 'aanya', 'tote-bags', 'Structured Tote Bag, Navy', '899', '2499', 'Navy Blue', [], 18, ['dj172'], ['Vegan leather', 'Fits a 13 in laptop', 'Zip pocket inside'], 'A roomy navy tote with sturdy handles for office days and weekend errands.', ['material' => 'Synthetic Leather']],
        ['bag-white-backpack', 'DEMO05', 'aanya', 'college-backpacks', 'Faux Leather Mini Backpack, White', '749', '1999', 'White', [], 21, ['dj175'], ['Drawstring top', 'Flap with metal bar', 'Adjustable straps'], 'A compact white backpack with a drawstring top and a flap closure.', ['material' => 'Synthetic Leather']],
        ['heels-black', 'DEMO05', 'aanya', 'heels', 'Classic Black Stiletto Pumps', '799', '1999', 'Black', ['UK 4', 'UK 5', 'UK 6', 'UK 7'], 24, ['dj188'], ['3.5 in stiletto heel', 'Cushioned insole', 'Pointed toe'], 'Timeless black pumps that go from the office to dinner.', ['material' => 'Synthetic Leather']],
        ['heels-red', 'DEMO05', 'aanya', 'heels', 'Glossy Red Party Heels', '749', '1899', 'Red', ['UK 4', 'UK 5', 'UK 6', 'UK 7'], 19, ['dj189'], ['Patent finish', '3 in heel', 'Padded footbed'], 'High-shine red heels for weddings and parties.', ['material' => 'Synthetic Leather']],

        // Men (Urban Thread Co).
        ['shirt-gingham', 'DEMO02', 'urban-thread', 'casual-shirts', 'Men Gingham Check Shirt, Blue & Black', '599', '1499', 'Blue', ['S', 'M', 'L'], 35, ['dj83'], ['100% cotton', 'Button-down collar', 'Regular fit'], 'A crisp gingham check shirt in pure cotton, easy to dress up or down.', ['fabric' => 'Cotton', 'pattern' => 'Checked', 'occasion' => 'Casual']],
        ['shirt-buffalo', 'DEMO02', 'urban-thread', 'casual-shirts', 'Men Buffalo Check Flannel Shirt, Red', '699', '1699', 'Red', ['S', 'M', 'L'], 27, ['dj85'], ['Brushed flannel', 'Two chest pockets', 'Regular fit'], 'Warm brushed-flannel shirt in a classic red and black buffalo check.', ['fabric' => 'Cotton', 'pattern' => 'Checked', 'occasion' => 'Casual']],
        ['shirt-tropical', 'DEMO02', 'urban-thread', 'casual-shirts', 'Men Tropical Print Half-Sleeve Shirt', '549', '1299', 'Navy Blue', ['S', 'M', 'L'], 32, ['dj86'], ['Viscose blend', 'Camp collar', 'Relaxed fit'], 'A vacation-ready camp-collar shirt with a hibiscus print.', ['fabric' => 'Viscose', 'pattern' => 'Floral', 'occasion' => 'Casual']],

        // Kitchen (RasoiCraft Kitchenware).
        ['chakla-belan', 'DEMO04', 'rasoi-craft', 'kitchen-gadgets', 'Wooden Chakla Belan Set', '349', '699', 'Brown', [], 60, ['dj76'], ['Sheesham wood', 'Non-slip base', 'Smooth-rolling belan'], 'A sturdy round chakla with a tapered belan for soft, even rotis every day.', ['material' => 'Wood']],
        ['spatula', 'DEMO04', 'rasoi-craft', 'kitchen-gadgets', 'Bamboo Cooking Spatula, Set of 2', '199', '399', 'Beige', [], 80, ['dj48'], ['Natural bamboo', 'Safe on non-stick', 'Heat resistant'], 'Light, strong bamboo spatulas that will not scratch non-stick pans.', ['material' => 'Wood']],
        ['chopping-board', 'DEMO04', 'rasoi-craft', 'kitchen-gadgets', 'Acacia Wood Chopping Board, Large', '449', '999', 'Brown', [], 40, ['dj53'], ['Solid acacia', '40 x 25 cm', 'Juice groove'], 'A thick acacia board that is gentle on knife edges and easy to clean.', ['material' => 'Wood']],
        ['chef-knife', 'DEMO04', 'rasoi-craft', 'kitchen-gadgets', 'Stainless Steel Chef Knife, 8 inch', '299', '799', 'Silver', [], 45, ['dj64'], ['High-carbon steel', 'Wooden handle', 'Full tang'], 'An all-purpose chef knife for chopping, slicing and dicing.', ['material' => 'Stainless Steel']],
        ['serving-tray', 'DEMO04', 'rasoi-craft', 'kitchen-gadgets', 'Bent Wood Serving Tray with Handles', '549', '1199', 'Brown', [], 25, ['dj75'], ['Bent plywood', 'Cut-out handles', '45 x 30 cm'], 'A modern serving tray for chai, snacks and breakfast in bed.', ['material' => 'Wood']],
        ['spice-rack', 'DEMO04', 'rasoi-craft', 'organisers', 'Revolving Spice Rack, 4 Jars', '399', '899', 'White', [], 38, ['dj73'], ['360 degree rotation', 'Glass jars', 'Shaker lids'], 'Keep everyday masalas within reach on a compact revolving stand.', ['material' => 'Glass']],
        ['mug-tree', 'DEMO04', 'rasoi-craft', 'organisers', 'Metal Mug Tree Stand, 6 Hooks', '299', '699', 'Black', [], 30, ['dj67'], ['Powder-coated metal', '6 hooks', 'Stable base'], 'A space-saving stand that keeps cups dry and organised.', ['material' => 'Metal']],
        ['blender', 'DEMO04', 'rasoi-craft', 'kitchen-appliances', 'Glass Jar Blender, 1.5 L', '2299', '4499', 'Silver', [], 15, ['dj51'], ['600 W motor', '1.5 L glass jar', '3 speeds and pulse'], 'Smoothies, chutneys and shakes in seconds with a sturdy glass jar.', ['material' => 'Glass']],
        ['hand-blender', 'DEMO04', 'rasoi-craft', 'kitchen-appliances', 'Hand Blender with Steel Shaft, 400 W', '999', '2199', 'Red', [], 26, ['dj61'], ['400 W', 'Detachable steel shaft', '2 speeds'], 'Purees, soups and lassi straight in the pot.', ['material' => 'Stainless Steel']],
        ['induction', 'DEMO04', 'rasoi-craft', 'kitchen-appliances', 'Glass-Top Induction Cooktop, 2000 W', '2799', '4999', 'Black', [], 12, ['dj56'], ['2000 W', 'Touch controls', '8 preset menus'], 'A fast, efficient induction cooktop with a toughened glass top.', ['material' => 'Glass']],
        ['microwave', 'DEMO04', 'rasoi-craft', 'kitchen-appliances', 'Solo Microwave Oven, 20 L', '5499', '8999', 'White', [], 7, ['dj66'], ['20 L capacity', '5 power levels', 'Defrost by weight'], 'Reheat, defrost and cook with a compact 20 L solo microwave. Prepaid only (above the COD limit).', ['material' => 'Metal']],
        ['wok', 'DEMO04', 'rasoi-craft', 'cookware-sets', 'Carbon Steel Wok with Wooden Handle', '649', '1299', 'Black', [], 22, ['dj52'], ['Pre-seasoned', '28 cm', 'Gas and induction'], 'A light carbon steel wok for stir-fries and tadka.', ['material' => 'Metal']],
        ['stock-pot', 'DEMO04', 'rasoi-craft', 'cookware-sets', 'Stainless Steel Stock Pot with Glass Lid, 5 L', '1099', '2199', 'Silver', [], 18, ['dj71'], ['Tri-ply base', 'Glass lid', 'Induction ready'], 'A 5 L pot for dal, biryani and big-batch cooking.', ['material' => 'Stainless Steel']],

        // Home decor (Gharelu Living & Gadgets).
        ['plant-areca', 'DEMO03', 'kosha-living', 'artificial-flowers', 'Artificial Areca Palm with Metal Stand', '899', '1999', 'Green', [], 20, ['dj45'], ['Lifelike leaves', 'Black metal stand', '60 cm tall'], 'All the green, none of the watering: a lifelike palm on a slim stand.', ['material' => 'Plastic']],
        ['plant-pot', 'DEMO03', 'kosha-living', 'artificial-flowers', 'Artificial Money Plant in Ceramic Pot', '499', '1099', 'Green', [], 34, ['dj46'], ['Ceramic pot', 'UV-resistant leaves', '30 cm'], 'A compact faux money plant that brightens desks and shelves.', ['material' => 'Ceramic']],
        ['table-lamp', 'DEMO03', 'kosha-living', 'showpieces', 'Vintage Table Lamp with Fabric Shade', '1299', '2999', 'Gold', [], 11, ['dj47'], ['Antique finish base', 'Fabric shade', 'E27 holder'], 'A warm vintage lamp for bedside tables and reading corners.', ['material' => 'Metal']],
        ['earphones', 'DEMO03', 'taal-audio', 'earbuds', 'In-Ear Wired Earphones with Mic, Pack of 3', '399', '999', 'Multicolour', [], 50, ['ov:earphones-wired'], ['3.5 mm jack', 'In-line mic', 'Tangle-free cable'], 'Three pairs of everyday wired earphones with an in-line mic.', []],
        ['bedsheet-black', 'DEMO03', 'kosha-living', 'bedding-sets', 'Microfibre Bedsheet Set, Solid Black', '799', '1799', 'Black', [], 26, ['ov:bedsheet-black'], ['Flat sheet, fitted sheet, 2 pillow covers', 'Wrinkle resistant', 'Queen size'], 'A soft microfibre sheet set in solid black, available in 8 colours.', ['material' => 'Fabric']],

        // Accessories and beauty (Noor Accessories).
        ['watch-steel', 'DEMO06', 'noor', 'men-watches', 'Men Minimal Steel Watch, Black Dial', '1299', '3499', 'Silver', [], 20, ['dj194'], ['Stainless steel strap', 'Quartz movement', '3 ATM water resistance'], 'A clean black-dial watch that suits every outfit.', ['material' => 'Stainless Steel']],
        ['watch-gold', 'DEMO06', 'noor', 'women-watches', 'Women Gold-Tone Bracelet Watch, Blue Dial', '1599', '3999', 'Gold', [], 14, ['dj193'], ['Gold-tone bracelet', 'Roman numerals', 'Quartz movement'], 'A gold-tone bracelet watch with a deep blue sunray dial.', ['material' => 'Alloy']],
        ['sunglasses-retro', 'DEMO06', 'noor', 'men-sunglasses', 'Half-Frame Retro Sunglasses, Tortoise', '499', '1299', 'Brown', [], 40, ['dj154'], ['UV400 lenses', 'Metal bridge', 'Hard case'], 'A retro half-frame with a tortoise brow line and dark lenses.', ['material' => 'Plastic']],
        ['sunglasses-aviator', 'DEMO06', 'noor', 'men-sunglasses', 'Metal Aviator Sunglasses, Gradient Lens', '549', '1499', 'Gold', [], 36, ['dj155'], ['Gradient lenses', 'Spring hinges', 'UV400'], 'Lightweight metal aviators with gradient lenses.', ['material' => 'Metal']],
        ['sunglasses-pilot', 'DEMO06', 'noor', 'women-sunglasses', 'Green Tint Pilot Sunglasses', '449', '1199', 'Green', [], 33, ['dj156'], ['Green tinted lenses', 'Double bridge', 'UV400'], 'Oversized pilot sunglasses with a soft green tint.', ['material' => 'Metal']],
        ['earring-emerald', 'DEMO06', 'noor', 'studs', 'Emerald Crystal Drop Earrings', '299', '799', 'Green', [], 45, ['dj182'], ['Faceted crystal', 'Gold-plated hook', 'Lightweight'], 'Elegant emerald-green crystal drops for festive evenings.', ['material' => 'Alloy']],
        ['earring-hoop', 'DEMO06', 'noor', 'hoops', 'Checkerboard Resin Hoop Earrings', '249', '599', 'Mint', [], 55, ['dj183'], ['Resin hoops', 'Stud top', 'Statement size'], 'Playful checkerboard hoops in mint and cream.', ['material' => 'Plastic']],
        ['earring-leaf', 'DEMO06', 'noor', 'studs', 'Monstera Leaf Statement Earrings', '279', '699', 'Gold', [], 50, ['dj184'], ['Matte gold finish', 'Leaf cut-out', 'Lightweight'], 'Oversized monstera leaf earrings with a matte gold finish.', ['material' => 'Alloy']],
        ['lipstick', 'DEMO06', 'glow-ritual', 'lipsticks', 'Matte Red Lipstick, Long Wear', '249', '499', 'Red', [], 90, ['dj4'], ['Long-wear matte', 'Vitamin E', 'Transfer resistant'], 'A true red matte lipstick that stays put for up to 8 hours.', []],
        ['nail-polish', 'DEMO06', 'glow-ritual', 'nail-polish', 'Gel-Finish Nail Polish, Cherry Red', '149', '299', 'Red', [], 120, ['dj5'], ['Gel shine, no lamp', 'Quick dry', '9 ml'], 'High-shine cherry red with a gel-like finish in two coats.', []],
        ['compact', 'DEMO06', 'glow-ritual', 'foundations', 'Mattifying Compact Powder', '299', '599', 'Beige', [], 70, ['dj3'], ['Oil control', 'SPF 15', 'Mirror inside'], 'A pressed compact that controls shine and sets makeup.', []],
    ];

    /**
     * Buyers: [first name, last name, email, phone, pincode, address line 1, address line 2, reseller business].
     * The first is DemoCatalogSeeder's demo buyer (kept, gets addresses).
     *
     * @var list<array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string, 6: string, 7: string|null}>
     */
    public const CUSTOMERS = [
        ['Demo', 'Buyer', 'demo.buyer@example.com', '+919876500001', '560001', 'Flat 402, Prestige Residency', 'MG Road, Ashok Nagar', null],
        ['Priya', 'Sharma', 'priya.demo@example.com', '+919876500002', '411001', '12 Shanti Kunj, FC Road', 'Shivajinagar', null],
        ['Rahul', 'Verma', 'rahul.demo@example.com', '+919876500003', '226001', 'B-17, Gomti Apartments', 'Hazratganj', null],
        ['Ananya', 'Iyer', 'ananya.demo@example.com', '+919876500004', '600001', '7, Second Street', 'George Town', null],
        ['Imran', 'Khan', 'imran.demo@example.com', '+919876500005', '500001', '3-5-120, Abids Road', 'Abids', 'Imran Collections'],
        ['Kavya', 'Nair', 'kavya.demo@example.com', '+919876500006', '682011', 'Door 21, Marine Drive', 'Ernakulam', null],
        ['Arjun', 'Mehta', 'arjun.demo@example.com', '+919876500007', '380001', '44, Swastik Society', 'Navrangpura', null],
        ['Sneha', 'Das', 'sneha.demo@example.com', '+919876500008', '700001', '9B, Park Street', 'Park Street Area', null],
    ];

    /**
     * Review texts by rating: [title, body].
     *
     * @var array<int, list<array{0: string, 1: string}>>
     */
    public const REVIEWS = [
        5 => [
            ['Exactly as shown', 'The colour and finish are exactly like the photos. Packed well and delivered before the date shown.'],
            ['Great value', 'Excellent quality for this price. I have already recommended it to my sister.'],
            ['Loved it', 'Very happy with the purchase. The fit is perfect and the material feels premium.'],
            ['Will buy again', 'Second time ordering from this seller and the quality is consistent. Quick delivery too.'],
        ],
        4 => [
            ['Good product', 'Nice quality and good finishing. Delivery took a day longer than expected but no complaints.'],
            ['Worth the money', 'Looks good and works well. The packaging could be a little better.'],
            ['Pretty good', 'Colour is slightly darker than the picture but still looks lovely.'],
        ],
        3 => [
            ['Okay for the price', 'Decent product, does the job. The size runs a little small, order one size up.'],
            ['Average', 'Quality is average. Fine for daily use but not for gifting.'],
        ],
    ];
}
