# Blog and FAQ

## The blog

**The posts come from kongtent** through `krausgebaut/kongtent-bundle`; this repository holds none of them. `/` shows the three newest (`HOMEPAGE_POST_LIMIT`), `/blog` all of them, and the sitemap lists every one. Every card shows its date rather than its rubric. **What the bundle enforces is in `../../../workbench/kongtent-bundle/README.md`; what every reading site decides—the key and the deploy check, the address, the privacy policy—is in `../../../docs/KONGTENT.md`.** What stands here is what this site decided.

**The references are posts like any other**, under a rubric Marcel keeps in kongtent. The site knows no rubric by name and shows the one a post carries above its headline. Their addresses under `/referenzen` answer 301 onto the same year and slug under `/blog`; where no post stands there, the redirect ends in 404.

**The route is `/blog/{year}/{slug}`**, and the year is the year of the post's date, compared in `BlogController`—kongtent is asked for the slug alone, and without the comparison one post would answer under every year.

**The site sets five block types itself**, under `templates/bundles/KongtentBundle/blocks/`: picture and gallery with their caption line, quotation on the accent rail, the term list as rows with the name in the mono label voice and the value in the body face, and the paragraph. Heading, list and embed render in the bundle's markup inside `prose`. Everything the site sets carries `not-prose`, and a link in generated markup outside `prose` gets its style from `[data-generated] a` in `public/css/input.css`.

**A paragraph that is one external link and nothing else is set as `_link_arrow` with the external icon**, opening in a new tab—the shape of a customer review. `App\Twig\SoleLinkExtension` recognizes it in the converter's markup; a link within this site stays running text, whether it is a path or an address on the host of the request, with or without `www.`.

**The feed carries the full text** and asks kongtent once per post, because the list carries no blocks.

**The references and their categories have no place in this project.** No entity, table, backend screen or upload stands for them; their addresses redirect, and a dump of both tables with the uploaded pictures is kept outside the workspace.

## The FAQ

**The questions are one content in kongtent**, slug `haeufig-gestellte-fragen`, fixed in `DefaultController::faq()` and not listed—without it the page answers 404—so the blog, the feed and the sitemap never see it, and `BlogController` refuses a content that is not listed as a post. Its head is the page's head: `headline` and `teaser` the title and its second line, `metaTitle` and `metaDescription` the document head.

**Every heading opens a question, whatever its level, and the blocks up to the next one are its answer.** **An answer is expected to be text.** Every block renders, but the picture and gallery templates state the width of the blog column in `sizes`; a picture in a two-column card would load a size larger than it needs. A heading without an answer and whatever stands before the first heading are dropped without a word; the editor keeps the content in shape. The `FAQPage` data carries the text of every question and answer through `plain_text`, tags stripped and entities decoded.
