import Faqs from "../../_common/classes/Faqs.js";
import FaqCategories from "../../_common/classes/FaqCategories.js";

const list = document.querySelector("[data-public-faq-list]");
const faqs = new Faqs();
const categories = new FaqCategories();

if (list) {
    loadFaqs().catch((error) => {
        list.textContent = "Não foi possível carregar as perguntas.";
        window.EduUI?.showToast(error, "error");
    });
}

function renderFaq(faq, index) {
    const item = document.createElement("article");
    item.className = "accordion-item";
    const button = document.createElement("button");
    button.className = "accordion-trigger";
    button.type = "button";
    button.setAttribute("aria-expanded", "false");
    button.id = `faq-${faq.id || index + 1}`;
    button.append(document.createTextNode(faq.question));
    const icon = document.createElement("em");
    icon.className = "accordion-icon";
    icon.setAttribute("aria-hidden", "true");
    icon.textContent = "▾";
    button.appendChild(icon);
    const content = document.createElement("section");
    content.className = "accordion-content";
    content.setAttribute("role", "region");
    content.setAttribute("aria-labelledby", button.id);
    const answer = document.createElement("p");
    answer.textContent = faq.answer;
    content.appendChild(answer);

    button.addEventListener("click", () => {
        const open = item.classList.contains("is-open");
        list.querySelectorAll(".accordion-item.is-open").forEach((openItem) => {
            openItem.classList.remove("is-open");
            openItem.querySelector(".accordion-trigger")?.setAttribute("aria-expanded", "false");
        });
        if (!open) {
            item.classList.add("is-open");
            button.setAttribute("aria-expanded", "true");
        }
    });

    item.append(button, content);
    return item;
}

async function loadFaqs() {
    const limit = Number(list.dataset.faqLimit || 0);
    const [faqResponse, categoryResponse] = await Promise.all([
        faqs.listAll(),
        limit ? Promise.resolve({ data: [] }) : categories.listAll()
    ]);
    const allFaqs = faqResponse.data || [];

    list.replaceChildren();
    if (!allFaqs.length) {
        list.textContent = "Nenhuma pergunta publicada no momento.";
        return;
    }

    // Na home mostra só as primeiras perguntas; na página de FAQ agrupa por categoria.
    if (limit) {
        allFaqs.slice(0, limit).forEach((faq, index) => list.appendChild(renderFaq(faq, index)));
        return;
    }

    let index = 0;
    const groups = categoryResponse.data.map((category) => [category.name, allFaqs.filter((faq) => faq.faqsCategoryId === category.id)]);
    const grouped = new Set(groups.flatMap(([, items]) => items));
    const others = allFaqs.filter((faq) => !grouped.has(faq));
    if (others.length) groups.push(["Outras perguntas", others]);

    groups.filter(([, items]) => items.length).forEach(([name, items]) => {
        const title = document.createElement("h2");
        title.className = "faq-category-title";
        title.textContent = name;
        list.appendChild(title);
        items.forEach((faq) => list.appendChild(renderFaq(faq, index++)));
    });
}
