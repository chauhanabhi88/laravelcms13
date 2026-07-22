<?php

namespace Modules\ImportReport\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Modules\ImportReport\Entities\CompetitorMapping;

class ProcessCompetitorUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $file;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(string $file)
    {
        $this->file = $file; 
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
            dump('process Competitor file ----'.$this->file);
        
            $data = array_map('str_getcsv',file($this->file));
            
            foreach($data as $row){

                if(isset($row[2]) && !empty($row[2])){

                    $url = parse_url($row[2]);

                    if(isset($url['host']) && !empty($url['host'])){
                        $row[2] = str_replace('www.', '', $url['host']);
                    }else{
                        $row[2] = str_replace('www.', '', substr($url['path'], 0, strpos($url['path'], "/")));
                        //substr give www.domain.com remove if there is sub domain after '/'.
                        //str_replace remove 'www.' from domain.
                    }
                }

                CompetitorMapping::insert([
                    'sku'     => $row[0],
                    'ref_sku'    => (isset($row[1]) && !empty($row[1]))?$row[1]:null,
                    'ref_url'    => (isset($row[2]) && !empty($row[2]))?$row[2]:null,
                    'ref_name'    => (isset($row[3]) && !empty($row[3]))?$row[3]:null,
                    'ref_product_exists'    => (isset($row[4]) && !empty($row[4]))?$row[4]:null, 
                    'ignor'    => (isset($row[5]) && !empty($row[5]))?$row[5]:null,
                    'send_in_feed'    => (isset($row[6]) && !empty($row[6]))?$row[6]:null,
                    'priority'    => (isset($row[7]) && !empty($row[7]))?$row[7]:null,
                    'piece_multiplier'    => (isset($row[8]) && !empty($row[8]))?$row[8]:null,
                    'piece_count'    => (isset($row[9]) && !empty($row[9]))?$row[9]:null,
                    'shipping_method'    => (isset($row[10]) && !empty($row[10]))?$row[10]:null,
                    'name'    => (isset($row[11]) && !empty($row[11]))?$row[11]:null,
                    'brand_value'    => (isset($row[12]) && !empty($row[12]))?$row[12]:null,
                    'mpn'    => (isset($row[13]) && !empty($row[13]))?$row[13]:null,
                ]);
            }

            dump('Done Competitor file ----'.$this->file);
            unlink($this->file);        
    }
}
