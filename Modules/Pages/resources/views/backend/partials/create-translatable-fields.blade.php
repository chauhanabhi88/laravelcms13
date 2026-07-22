{{ i18nInput("title",trans("pages::pages.labels.title"), $errors,$lang,null,['class' => 'form-control required',"data-slug" => ($lang == App::getLocale() ? "source" : "")])}}

{{ i18nTextarea("body",trans('pages::pages.labels.body'), $errors,$lang,null,["class" => "form-control required formated-textarea", 'style'=>"height: 620.2px;", "id" => "body"])}}

{{ i18nInput("meta_title",trans("pages::pages.labels.meta_title"), $errors,$lang,null,['class' => 'form-control required'])}}

{{ i18nTextarea("meta_description",trans('pages::pages.labels.meta_description'), $errors,$lang,null,["class" => "form-control",'placeholder' => trans('pages::pages.labels.meta_description'), 'style'=>"height: 200.2px;"])}}