{{ i18nInput("title",trans('banner::banner.labels.title'), $errors,$lang,$banner,['class' => 'form-control required',"data-slug" => ($lang == App::getLocale() ? "source" : "")])}}

{{ i18nTextarea("content",trans('banner::banner.labels.content'), $errors,$lang,$banner,["class" => "form-control required","placeholder" => trans('banner::banner.labels.content')])}}