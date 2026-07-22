<?php


namespace Modules\ImportReport\Imports;

ini_set('max_execution_time',9000);
ini_set('memory_limit','1024M');

//use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Imports\HeadingRowFormatter;
use Modules\ImportReport\Entities\PrisyncVerticalReport;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithUpserts;


//use Maatwebsite\Excel\Concerns\RemembersRowNumber;

//HeadingRowFormatter::default('none');

class PrisyncVerticalReportImport implements ToModel,WithHeadingRow
{   
    public function model(array $row)
    {
       
        try {
            
            $row['site'] = substr($row['site'], 0, strpos($row['site'], " "));
            

            $urlList= array('colemanfurniture.com',
            'furnitureetc.com',
            'homegallerystores.com',
            'amazon.com',
            'appliancesconnection.com',
            'ashleyfurniture.com',
            'bedroomfurniturediscounts.com',
            'cymax.com',
            'diningroomsoutlet.com',
            'discountlivingrooms.com',
            'furniturecart.com',
            'goedekers.com',
            'hayneedle.com',
            'homesquare.com',
            'overstock.com',
            'theclassyhome.com',
            'tvstandsoutlet.com',
            'unlimitedfurnituregroup.com');

            if(in_array($row['site'],$urlList)){

                return new PrisyncVerticalReport([
                    'product_name'     => (isset($row['product_name']) && (!empty($row['product_name']) && $row['product_name']!='-'))?$row['product_name']:'',
                    'product_code'    => (isset($row['product_code']) && (!empty($row['product_code']) && $row['product_code']!='-'))?$row['product_code']:'',
                    'barcode'    => $row['barcode'],
                    'brand'    => $row['brand'],
                    'category'    => $row['category'], 
                    'product_tags'    => $row['product_tags'],
                    'number_of_matches'    => (isset($row['number_of_matches']) && (!empty($row['number_of_matches']) && is_int($row['number_of_matches'])))?$row['number_of_matches']:null,
                    'index'    => (isset($row['index']) && (!empty($row['index']) && is_int($row['index'])))?$row['index']:null,
                    'position'    => $row['position'],
                    'cheapest_site'    => $row['cheapest_site'],
                    'highest_site'    => $row['highest_site'],
                    'minimum_price'    => (isset($row['minimum_price']) && (!empty($row['minimum_price']) && is_int($row['minimum_price'])))?$row['minimum_price']:null,
                    'maximum_price'    => (isset($row['maximum_price']) && (!empty($row['maximum_price']) && is_int($row['maximum_price'])))?$row['maximum_price']:null,
                    'average_price'    => (isset($row['average_price']) && (!empty($row['average_price']) && is_int($row['average_price'])))?$row['average_price']:null,
                    'my_price'    => (isset($row['my_price']) && (!empty($row['my_price']) && is_int($row['my_price'])))?$row['my_price']:null,
                    'product_cost'    => (isset($row['product_cost']) && (!empty($row['product_cost']) && is_int($row['product_cost'])))?$row['product_cost']:null,
                    'smart_price'    => $row['smartprice'],
                    'last_update_cycle'    => $row['last_update_cycle'],
                    'site'    => $row['site'],
                    'site_index'    => (isset($row['site_index']) && (!empty($row['site_index']) && is_int($row['site_index'])))?$row['site_index']:null,
                    'price'    => (isset($row['price']) && (!empty($row['price']) && is_int($row['price'])))?$row['price']:null,
                    'change_direction'    => $row['change_direction'],
                    'stock'    => $row['stock'],
                ]);
            }
            

            
        } catch (Exception $e) {
            //echo $e->getMessage();
            //die();
            $failures = $e->failures();

            foreach ($failures as $key => $failure) {
                $failure->errors();
            }
        }
    }
    /*public function uniqueBy()
    {
        return 'sku';
    }*/
    public function batchSize(): int
    {
        return 500000;
    }
    public function chunkSize(): int
    {
        return 500000;
    }
  
}