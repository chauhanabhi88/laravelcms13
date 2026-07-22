{{ i18nInput("title",trans('block::block.labels.title'), $errors,$lang,null,['class' => 'form-control required',"data-slug" => ($lang == App::getLocale() ? "source" : "")])}}

{{ i18nTextarea("content",trans('block::block.labels.content'), $errors,$lang,null,["class" => "form-control required","placeholder" => trans('block::block.labels.content')])}}