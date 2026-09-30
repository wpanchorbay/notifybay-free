/**
 * POST to a WordPress admin-post.php endpoint by building a form and submitting it.
 *
 * Two reasons this is not a plain link or a JSX <form>.
 *
 * **POST, not GET.** One caller sends a live application password. A GET would
 * put that credential in the URL bar, in browser history, and in every access
 * log between the browser and the server.
 *
 * **Built on <body>, not rendered.** These panels live inside WooCommerce's
 * `<form id="mainform">`, and a form nested inside a form is not something to be
 * clever about: the HTML parser drops it outright, React builds it through the
 * DOM so it survives, and the difference is exactly the kind of thing that works
 * in development and not on someone's store. Appending outside `#mainform` means
 * the question never arises -- and it also means the submit cannot be picked up
 * by WooCommerce's own save handlers, which watch that form.
 *
 * The response is either a file download or a redirect, so the page either stays
 * put or navigates deliberately. Nothing here needs to clean up after itself
 * beyond removing the element.
 *
 * @param url    Nonced admin-post.php URL, action included.
 * @param fields Extra body fields. Empty values are skipped.
 */
export function postTo(url: string, fields: Record<string, string | null | undefined> = {}) {
  const form = document.createElement("form");

  form.method = "post";
  form.action = url;
  form.style.display = "none";

  Object.entries(fields).forEach(([name, value]) => {
    if (!value) {
      return;
    }

    const field = document.createElement("input");

    field.type = "hidden";
    field.name = name;
    field.value = value;
    form.appendChild(field);
  });

  document.body.appendChild(form);
  form.submit();
  document.body.removeChild(form);
}
