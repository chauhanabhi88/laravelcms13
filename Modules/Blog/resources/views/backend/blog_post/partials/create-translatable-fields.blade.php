
 {{ i18nInput("title",trans('blog::blog_post.labels.title'), $errors,$lang,null,['class' => 'form-control required',"data-slug" => ($lang == App::getLocale() ? "source" : "")])}}
 {{ i18nTextarea("short_content",trans('blog::blog_post.labels.short_content'), $errors,$lang,null,["class" => "form-control formated-textarea required", "id" => "blog-post-short-content"])}}
 {{ i18nTextarea("content",trans('blog::blog_post.labels.content'), $errors,$lang,null,["class" => "form-control formated-textarea required", "id" => "blog-post-content" ])}}
 {{ i18nInput("meta_keywords",trans('blog::blog_post.labels.meta_keywords'), $errors,$lang,null,['class' => 'form-control required'])}}
 {{ i18nTextarea("meta_description",trans('blog::blog_post.labels.meta_description'), $errors,$lang,null,["class" => "form-control required"])}}


