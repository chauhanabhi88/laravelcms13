
{{ i18nInput("name",trans('banner::banner_group.labels.name'), $errors,$lang,$bannerGroup,['class' => 'form-control required',"data-slug" => ($lang == App::getLocale() ? "source" : "")])}}