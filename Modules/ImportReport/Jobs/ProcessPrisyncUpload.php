<?php

namespace Modules\ImportReport\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Modules\ImportReport\Entities\PrisyncVerticalReport;

class ProcessPrisyncUpload implements ShouldQueue
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
        dump('process Prisync file ----'.$this->file);
        
            $data = array_map('str_getcsv',file($this->file));
            
            foreach($data as $row){

                if(isset($row[18]) && !empty($row[18])){
                    $row[18] = substr($row[18], 0, strpos($row[18], " "));
                }

                PrisyncVerticalReport::insert([
                    'product_name'     => (isset($row[0]) && (!empty($row[0]) && $row[0]!='-'))?$row[0]:null,
                    'product_code'    => (isset($row[1]) && (!empty($row[1]) && $row[1]!='-'))?$row[1]:null,
                    'barcode'    => (isset($row[2]) && !empty($row[2]))?$row[2]:null,
                    'brand'    => (isset($row[3]) && !empty($row[3]))?$row[3]:null,
                    'category'    => (isset($row[4]) && !empty($row[4]))?$row[4]:null, 
                    'product_tags'    => (isset($row[5]) && !empty($row[5]))?$row[5]:null,
                    'number_of_matches'    => (isset($row[6]) && (!empty($row[6]) && is_int($row[6])))?$row[6]:null,
                    'index'    => (isset($row[7]) && (!empty($row[7]) && is_int($row[7])))?$row[7]:null,
                    'position'    => (isset($row[8]) && !empty($row[8]))?$row[8]:null,
                    'cheapest_site'    => (isset($row[9]) && !empty($row[9]))?$row[9]:null,
                    'highest_site'    => (isset($row[10]) && !empty($row[10]))?$row[10]:null,
                    'minimum_price'    => (isset($row[11]) && (!empty($row[11]) && is_int($row[11])))?$row[11]:null,
                    'maximum_price'    => (isset($row[12]) && (!empty($row[12]) && is_int($row[12])))?$row[12]:null,
                    'average_price'    => (isset($row[13]) && (!empty($row[13]) && is_int($row[13])))?$row[13]:null,
                    'my_price'    => (isset($row[14]) && (!empty($row[14]) && is_int($row[14])))?$row[14]:null,
                    'product_cost'    => (isset($row[15]) && (!empty($row[15]) && is_int($row[15])))?$row[15]:null,
                    'smart_price'    => (isset($row[16]) && !empty($row[16]))?$row[16]:null,
                    'last_update_cycle'    => (isset($row[17]) && !empty($row[17]))?$row[17]:null,
                    'site'    => (isset($row[18]) && !empty($row[18]))?$row[18]:null,
                    'site_index'    => (isset($row[19]) && (!empty($row[19]) && is_int($row[19])))?$row[19]:null,
                    'price'    => (isset($row[20]) && (!empty($row[20]) && is_int($row[20])))?$row[20]:null,
                    'change_direction'    => (isset($row[21]) && !empty($row[21]))?$row[21]:null,
                    'stock'    => (isset($row[22]) && !empty($row[22]))?$row[22]:null,
                ]);
            }

            dump('Done Prisync file ----'.$this->file);
            unlink($this->file);
    }
}
