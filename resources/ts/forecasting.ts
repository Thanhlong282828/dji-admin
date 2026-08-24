const button = document.querySelector<HTMLButtonElement>('[data-add-scenario-line]');
const template = document.querySelector<HTMLTemplateElement>('[data-scenario-template]');
const body = document.querySelector<HTMLTableSectionElement>('[data-scenario-lines]');

button?.addEventListener('click', () => {
    if (!template || !body) return;
    const count = body.querySelectorAll('.scenario-line').length;
    if (count >= 50) return;
    const row = template.content.cloneNode(true) as DocumentFragment;
    row.querySelectorAll<HTMLInputElement | HTMLSelectElement>('[data-name]').forEach((element) => {
        element.name = `scenario[${count}][${element.dataset.name ?? ''}]`;
    });
    body.appendChild(row);
});
