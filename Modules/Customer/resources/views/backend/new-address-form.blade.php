
{{ formStart(null,"POST" ,'admin.customer.address' ,updateUrlParams(), ['id' => 'address_form','enctype'=>'multipart/form-data'])}}
<div class="mb-2" id="addressDiv">
  <input type="hidden" name="customer_id" id="customerId" value="{{$customer->id}}">
  <input type="hidden" name="address_id" id="addressId">

  <label for="street_name">{{trans("customer::customer.labels.street_name")}} *</label>
  {{ normalTextarea("address[street_name]",'Block / Streetname', $errors,null,["class" => "form-control required",'id' => 'streetName','rows' => 5,'hide_label'=>'true'])}}
  <div class="row">
    <div class="col-md-6">
      <label for="building">{{trans("customer::customer.labels.building")}} *</label>
      {{ normalText("address[building]","Building / House No", $errors,null,['class' => 'form-control required','id'=>'building','hide_label'=>'true'])}}
    </div>
    <div class="col-md-6">
      <label for="unitNo">{{trans("customer::customer.labels.unit_no")}} *</label>
      {{ normalText("address[unit_no]","Unit No", $errors,null,['class' => 'form-control required','id'=>'unitNo','hide_label'=>'true'])}}
    </div>
  </div>
  <div class="row">
    <div class="col-md-6">
      <label for="postalCode">{{trans("customer::customer.labels.postal_code")}} *</label>
      {{ normalText("address[postal_code]","Postal Code", $errors,null,['class' => 'form-control required','id'=>'postalCode','hide_label'=>'true'])}}
    </div>
    <div class="col-md-6">
      <label for="tag">{{trans("customer::customer.labels.tag")}} *</label>
      {{ normalText("address[tag]","Tag", $errors,null,['class' => 'form-control required','id'=>'tag','hide_label'=>'true'])}}
    </div>
  </div>

  <div class="row mb-2 mt-2">
    <div class="col-md-1">
      <label for="isDefault">{{trans("customer::customer.labels.is_default")}}</label>
    </div>
    <div class="col-md-1">
      <label class="switch">
        <input type="checkbox" name="address[is_default_address]" class="" id="isDefault">
        <span class="slider round"></span>
      </label>
      </label>
    </div>
  </div>

  <button class="btn btn-primary btn-fw" id="saveNewAddress">{{trans("customer::customer.buttons.save")}}</button>
  <button class="btn btn-secondary btn-fw" type="button" id="addressFormCancel">{{trans("customer::customer.buttons.cancel")}}</button>
</div>
{{ formEnd() }}