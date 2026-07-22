<?php


namespace Modules\ImportReport\Imports;

ini_set('max_execution_time',9000);
ini_set('memory_limit','2024M');


//use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Imports\HeadingRowFormatter;
use Modules\ImportReport\Entities\CompetitorMapping;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithUpserts;


//use Maatwebsite\Excel\Concerns\RemembersRowNumber;

//HeadingRowFormatter::default('none');

class CompetitorMappingImport implements ToModel,WithHeadingRow,WithBatchInserts,WithChunkReading
{   
    public function model(array $row)
    {
        //dd($row);
       
        try {

            $url = parse_url($row['ref_url']);

            if(isset($url['host']) && !empty($url['host'])){
                $row['ref_url'] = str_replace('www.', '', $url['host']);
            }else{
                $row['ref_url'] = str_replace('www.', '', substr($url['path'], 0, strpos($url['path'], "/")));
                //substr give www.domain.com remove if there is sub domain after '/'.
                //str_replace remove 'www.' from domain.
            }

            
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

            if(in_array($row['ref_url'],$urlList)){

                return new CompetitorMapping([
                    'sku'     => $row['sku'],
                    'ref_sku'    => $row['ref_sku'],
                    'ref_url'    => $row['ref_url'],
                    'ref_name'    => $row['ref_name'],
                    'ref_product_exists'    => $row['ref_product_exists'], 
                    'ignor'    => $row['ignor'],
                    'send_in_feed'    => $row['send_in_feed'],
                    'priority'    => $row['priority'],
                    'piece_multiplier'    => $row['piece_multiplier'],
                    'piece_count'    => $row['piece_count'],
                    'shipping_method'    => $row['shipping_method'],
                    'name'    => $row['name'],
                    'brand_value'    => $row['brand_value'],
                    'mpn'    => $row['mpn'],
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
        return 5000;
    }
    public function chunkSize(): int
    {
        return 5000;
    }
  
}