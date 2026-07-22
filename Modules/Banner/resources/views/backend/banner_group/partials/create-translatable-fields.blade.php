{{ i18nInput("name",trans('banner::banner_group.labels.name'), $errors,$lang,null,['class' => 'form-control required',"data-slug" => ($lang == App::getLocale() ? "source" : "")])}}
