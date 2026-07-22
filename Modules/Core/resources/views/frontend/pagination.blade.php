<?php
// config
$link_limit = 5; // maximum number of links (a little bit inaccurate, but will be ok for now)
$nextPage = $collection->currentPage() + 1;
$prevPage =  $collection->currentPage() - 1;

?>
@if($collection->lastPage() != 1)

<ul class="pagination-wrp">
    @if ($collection->currentPage() != 1)
        <li data-page={{ $prevPage }}>
            <a href="javascript:void(0);" class="prev pg "><span></span></a>
         </li>
    @endif
        @for ($i = 1; $i <= $collection->lastPage(); $i++)
        <?php
            $half_total_links = floor($link_limit / 2);
            $from = $collection->currentPage() - $half_total_links;
            $to = $collection->currentPage() + $half_total_links;
            if ($collection->currentPage() < $half_total_links) {
               $to += $half_total_links - $collection->currentPage();
            }
            if ($collection->lastPage() - $collection->currentPage() < $half_total_links) {
                $from -= $half_total_links - ($collection->lastPage() - $collection->currentPage()) - 1;
            }
            ?>
            @if ($from < $i && $i < $to)
                <li class=""  data-page={{$i}}>
                    <a href="javascript:void(0);" class="{{ ($collection->currentPage() == $i) ? ' active' : '' }} pg" >{{ $i }}</a>
                </li>
            @endif
        @endfor
        @if ($collection->currentPage() != $collection->lastPage())
            <li data-page={{ $nextPage }}>
                <a href="javascript:void(0);" class="next pg "><span></span></a>
            </li>
        @endif
        {{ normalHidden("per_page",'' , 'per_page' ,['id' => 'current_page'])}}
</ul>
@endif