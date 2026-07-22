<label for="street_name">{{trans("customer::customer.labels.street_name")}} *</label>
{{ normalTextarea("address[street_name]",'Block / Streetname', $errors,null,["class" => "form-control required",'rows' => 5,'hide_label'=>'true'])}}
<div class="row">
	<div class="col-md-6">
		<label for="building">{{trans("customer::customer.labels.building")}} *</label>
		{{ normalText("address[building]","Building / House No", $errors,null,['class' => 'form-control required','hide_label'=>'true'])}}
	</div>
	<div class="col-md-6">
		<label for="unitNo">{{trans("customer::customer.labels.unit_no")}} *</label>
		{{ normalText("address[unit_no]","Unit No", $errors,null,['class' => 'form-control required','hide_label'=>'true'])}}
	</div>
</div>

<div class="row">
	<div class="col-md-6">
		<label for="postalCode">{{trans("customer::customer.labels.postal_code")}} *</label>
		{{ normalText("address[postal_code]","Postal Code", $errors,null,['class' => 'form-control required','hide_label'=>'true'])}}
	</div>
	<div class="col-md-6">
		<label for="tag">{{trans("customer::customer.labels.tag")}} *</label>
		{{ normalText("address[tag]","Tag", $errors,null,['class' => 'form-control required','hide_label'=>'true'])}}
	</div>
</div>











