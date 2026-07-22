 {{ i18nInput("title",trans('blog::blog_category.labels.title'), $errors,$lang,null,['class' => 'form-control required',"data-slug" => ($lang == App::getLocale() ? "source" : "")])}}
 {{ i18nTextarea("description",trans('blog::blog_category.labels.description'), $errors,$lang,null,["class" => "form-control formated-textarea required", "id" => 'blog-category-discription'])}}

 {{ i18nInput("meta_keywords",trans('blog::blog_category.labels.meta_keywords'), $errors,$lang,null,['class' => 'form-control required'])}}
 {{ i18nTextarea("meta_description",trans('blog::blog_category.labels.meta_description'), $errors,$lang,null,["class" => "form-control required"])}}

