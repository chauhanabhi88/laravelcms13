{{--
    Size chart form shared by create and edit. The measurement grid is edited in place:
    columns[] are the measurement names, rows[i][size] + rows[i][values][] one size each.
--}}
@php
    $chart = $catalogSizeChart ?? null;
    $columns = array_values((array) old('columns', $chart?->columns ?? ['Bust', 'Waist', 'Length']));
    $rows = array_values((array) old('rows', $chart?->rows ?? [['size' => 'S', 'values' => []], ['size' => 'M', 'values' => []], ['size' => 'L', 'values' => []]]));
    $isEnabled = (int) old('status', $chart?->status ?? config('core.enabled')) === (int) config('core.enabled');
@endphp
<div class="card card-info card-outline">
    <div class="card-header"><strong>{{ trans('catalog::catalog_size_chart.titles.general') }}</strong></div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-8">
                {{ normalText('name', 'catalog::catalog_size_chart.labels.name', $errors, old('name', $chart?->name), ['class' => 'form-control required', 'maxlength' => 120]) }}
            </div>
            <div class="col-md-4">
                {{ normalSelect('unit', 'catalog::catalog_size_chart.labels.unit', $errors, $unitOptions, old('unit', $chart?->unit ?? 'in')) }}
            </div>
        </div>

        <div class="form-group">
            <label for="image">{{ trans('catalog::catalog_size_chart.labels.image') }}</label>
            @if ($chart?->image)
                <div class="mb-2">
                    <img src="{{ getImageUrl(['module' => config('catalog.upload.size_chart'), 'image' => $chart->image, 'defualt-image' => true]) }}" alt="" style="max-height: 120px" class="img-thumbnail">
                    <label class="ml-2"><input type="checkbox" name="remove_image" value="1"> {{ trans('catalog::catalog_category.labels.remove_image') }}</label>
                </div>
            @endif
            <input type="file" id="image" name="image" class="form-control-file" accept="image/png,image/jpeg,image/webp">
            @error('image')<label class="error">{{ $message }}</label>@enderror
        </div>

        <label>{{ trans('catalog::catalog_size_chart.labels.status') }}</label>
        <div>
            <label class="switch">
                <input type="checkbox" value="{{ config('core.enabled') }}" name="status" {{ $isEnabled ? 'checked' : '' }}>
                <span class="slider round"></span>
            </label>
        </div>
    </div>
</div>

<div class="card card-info card-outline mt-3">
    <div class="card-header"><strong>{{ trans('catalog::catalog_size_chart.titles.grid') }}</strong></div>
    <div class="card-body">
        <p class="text-muted">{{ trans('catalog::catalog_size_chart.labels.grid_help') }}</p>
        @foreach (['columns', 'rows'] as $gridField)
            @error($gridField)<div class="alert alert-danger py-2">{{ $message }}</div>@enderror
        @endforeach
        @if ($errors->has('columns.*') || $errors->has('rows.*'))
            <div class="alert alert-danger py-2">
                @foreach (collect($errors->get('columns.*'))->merge($errors->get('rows.*'))->flatten()->unique() as $message)
                    <div>{{ $message }}</div>
                @endforeach
            </div>
        @endif

        <div class="table-responsive">
            <table class="table table-bordered table-sm size-chart-grid" data-size-chart-grid>
                <thead>
                    <tr data-grid-head>
                        <th>{{ trans('catalog::catalog_size_chart.labels.size') }}</th>
                        @foreach ($columns as $column)
                            <th data-grid-column>
                                <div class="input-group input-group-sm">
                                    <input type="text" name="columns[]" value="{{ $column }}" maxlength="40" class="form-control" required aria-label="{{ trans('catalog::catalog_size_chart.labels.column') }}">
                                    <div class="input-group-append"><button type="button" class="btn btn-outline-danger" data-remove-column title="{{ trans('catalog::catalog_size_chart.labels.remove') }}">&times;</button></div>
                                </div>
                            </th>
                        @endforeach
                        <th></th>
                    </tr>
                </thead>
                <tbody data-grid-body>
                    @foreach ($rows as $rowIndex => $row)
                        <tr data-grid-row>
                            <td><input type="text" name="rows[{{ $rowIndex }}][size]" value="{{ $row['size'] ?? '' }}" maxlength="20" class="form-control form-control-sm" required aria-label="{{ trans('catalog::catalog_size_chart.labels.size') }}"></td>
                            @foreach ($columns as $columnIndex => $column)
                                <td><input type="text" name="rows[{{ $rowIndex }}][values][]" value="{{ $row['values'][$columnIndex] ?? '' }}" maxlength="20" class="form-control form-control-sm" aria-label="{{ $column }}"></td>
                            @endforeach
                            <td><button type="button" class="btn btn-sm btn-outline-danger" data-remove-row title="{{ trans('catalog::catalog_size_chart.labels.remove') }}">&times;</button></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <button type="button" class="btn btn-sm btn-outline-primary" data-add-row><i class="fas fa-plus"></i> {{ trans('catalog::catalog_size_chart.labels.add_row') }}</button>
        <button type="button" class="btn btn-sm btn-outline-primary" data-add-column><i class="fas fa-plus"></i> {{ trans('catalog::catalog_size_chart.labels.add_column') }}</button>
    </div>
</div>

@push('js-stack')
<script type="text/javascript">
    (function($) {
        var $grid = $('[data-size-chart-grid]');
        var removeLabel = @json(trans('catalog::catalog_size_chart.labels.remove'));

        /* Row inputs are renamed after every change so rows[i] stays contiguous. */
        function renumber() {
            $grid.find('[data-grid-row]').each(function(rowIndex) {
                $(this).find('input').each(function(cellIndex) {
                    this.name = cellIndex === 0 ? 'rows[' + rowIndex + '][size]' : 'rows[' + rowIndex + '][values][]';
                });
            });
        }

        function valueCell() {
            return $('<td>').append($('<input type="text" maxlength="20" class="form-control form-control-sm">'));
        }

        $('[data-add-row]').on('click', function() {
            var $row = $('<tr data-grid-row>').append($('<td>').append($('<input type="text" maxlength="20" class="form-control form-control-sm" required>')));
            $grid.find('[data-grid-column]').each(function() {
                $row.append(valueCell());
            });
            $row.append($('<td>').append($('<button type="button" class="btn btn-sm btn-outline-danger" data-remove-row>&times;</button>').attr('title', removeLabel)));
            $grid.find('[data-grid-body]').append($row);
            renumber();
            $row.find('input').first().trigger('focus');
        });

        $('[data-add-column]').on('click', function() {
            var $head = $('<th data-grid-column>').append(
                $('<div class="input-group input-group-sm">')
                    .append($('<input type="text" name="columns[]" maxlength="40" class="form-control" required>'))
                    .append($('<div class="input-group-append">').append($('<button type="button" class="btn btn-outline-danger" data-remove-column>&times;</button>').attr('title', removeLabel)))
            );
            $grid.find('[data-grid-head] th').last().before($head);
            $grid.find('[data-grid-row]').each(function() {
                $(this).find('td').last().before(valueCell());
            });
            renumber();
            $head.find('input').trigger('focus');
        });

        $grid.on('click', '[data-remove-row]', function() {
            if ($grid.find('[data-grid-row]').length > 1) {
                $(this).closest('tr').remove();
                renumber();
            }
        });

        $grid.on('click', '[data-remove-column]', function() {
            var $columns = $grid.find('[data-grid-column]');
            if ($columns.length < 2) {
                return;
            }
            var index = $columns.index($(this).closest('th')) + 1;
            $(this).closest('th').remove();
            $grid.find('[data-grid-row]').each(function() {
                $(this).children('td').eq(index).remove();
            });
            renumber();
        });

        $('#main_form').validate({
            submitHandler: function(form) {
                if (!beenSubmitted) {
                    beenSubmitted = true;
                    loaderShow();
                    form.submit();
                }
            }
        });
    })(jQuery);
</script>
@endpush
