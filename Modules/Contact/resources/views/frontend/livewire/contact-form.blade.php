<div class="about-us">
    <div class="blue-bg">
        <h2>{{ trans('contact::contact_front.titles.contact_us') }}</h2>
    </div>
    <div class="login-page about-us-container">
        <div class="main-content">
            <div class="login-inner-wrapper">
                <div class="row">
                    <div class="col-lg-8 col-12 login">
                        {{ livewireFormStart("POST", ['id' => 'contact-form','enctype'=>'multipart/form-data']) }}
                        <div class="row">
                            <div class="form-group col-lg-6 f-child ">
                                {{ livewireText("name", "contact::contact_front.labels.name", null, ["class" => "form-control required"]) }}
                                <label class="error" for="name"> @error('name') {{ $message }} @enderror</label>
                            </div>
                            <div class="form-group col-lg-6 ">
                                {{ livewireText("email", "contact::contact_front.labels.email", null,["class" => "form-control required"]) }}
                                <label class="error" for="email"> @error('email') {{ $message }} @enderror</label>
                            </div>
                        </div>
                        <div class="row">
                            <div class="form-group col-lg-12">
                                {{ livewireTextarea("content", "contact::contact_front.labels.enter_question", null, ["class" => "form-control required"]) }}
                                <label class="error" for="content"> @error('content') {{ $message }} @enderror</label>
                            </div>
                        </div>
                        
                        <button class="btn btn-primary save" type="submit">{{trans('contact::contact_front.buttons.save')}}</button>
                        <div wire:loading.inline-flex>
                            <img width="50" src="{{ Module::asset('theme:frontend/images/spinner-loading.webp') }}" alt="Saving data..." />
                        </div>
                        {{ livewireFormEnd() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>