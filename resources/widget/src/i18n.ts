export const SUPPORTED = ['en', 'fr', 'es', 'de'] as const;

export type Locale = (typeof SUPPORTED)[number];

const en = {
    launcher: 'Help',
    title: 'Help',
    close: 'Close',
    intro: 'Ask a question about {app}. Answers come from the {app} documentation.',
    placeholder: 'Ask a question…',
    send: 'Send',
    thinking: 'Looking in the docs…',
    declined: 'I can only help with questions about {app}, and I could not find this in the documentation.',
    failed: 'Something went wrong while answering.',
    human: 'Talk to a person',
    escalated: 'We have passed your conversation to our team. You will get the reply by email, and it will show here too.',
    agent: 'Support team',
    sources: 'Sources',
    newChat: 'New conversation',
    errBusy: 'Too many questions at once. Please wait a minute.',
    errGeneric: 'We could not send your message. Please try again.',
    errLong: 'This conversation is too long. Start a new one.',
    tabAsk: "Ask",
    tabDocs: "Docs",
    tabFaq: "FAQ",
    searchDocs: "Search the docs…",
    openHelpCentre: "Open the help centre",
    noResults: "Nothing found. Try other words, or ask the assistant.",
    faqEmpty: "No frequently asked questions yet.",
    askInstead: "Didn't find it?",
    askAssistant: "Ask the assistant",
    loading: "Loading…",
    loadFailed: "Could not load this. Please try again.",
};

export type MessageKey = keyof typeof en;

// Inserted as text, never as markup.
const MESSAGES: Record<Locale, Record<MessageKey, string>> = {
    en,
    fr: {
        launcher: 'Aide',
        title: 'Aide',
        close: 'Fermer',
        intro: 'Posez une question sur {app}. Les réponses viennent de la documentation de {app}.',
        placeholder: 'Posez votre question…',
        send: 'Envoyer',
        thinking: 'Recherche dans la documentation…',
        declined: "Je ne peux répondre qu'aux questions sur {app}, et je n'ai pas trouvé cela dans la documentation.",
        failed: 'Un problème est survenu pendant la réponse.',
        human: 'Parler à une personne',
        escalated:
            'Nous avons transmis votre conversation à notre équipe. Vous recevrez la réponse par e-mail, et elle apparaîtra aussi ici.',
        agent: 'Équipe support',
        sources: 'Sources',
        newChat: 'Nouvelle conversation',
        errBusy: 'Trop de questions à la fois. Patientez une minute.',
        errGeneric: "Votre message n'a pas pu être envoyé. Réessayez.",
        errLong: 'Cette conversation est trop longue. Commencez-en une nouvelle.',
        tabAsk: "Demander",
        tabDocs: "Docs",
        tabFaq: "FAQ",
        searchDocs: "Rechercher dans la documentation…",
        openHelpCentre: "Ouvrir le centre d'aide",
        noResults: "Rien trouvé. Essayez d'autres mots, ou demandez à l'assistant.",
        faqEmpty: "Pas encore de questions fréquentes.",
        askInstead: "Pas trouvé ?",
        askAssistant: "Demander à l'assistant",
        loading: "Chargement…",
        loadFailed: "Impossible de charger. Réessayez.",
    },
    es: {
        launcher: 'Ayuda',
        title: 'Ayuda',
        close: 'Cerrar',
        intro: 'Haz una pregunta sobre {app}. Las respuestas vienen de la documentación de {app}.',
        placeholder: 'Escribe tu pregunta…',
        send: 'Enviar',
        thinking: 'Buscando en la documentación…',
        declined: 'Solo puedo ayudar con preguntas sobre {app}, y no encontré esto en la documentación.',
        failed: 'Algo salió mal al responder.',
        human: 'Hablar con una persona',
        escalated:
            'Hemos pasado tu conversación a nuestro equipo. Recibirás la respuesta por correo y también aparecerá aquí.',
        agent: 'Equipo de soporte',
        sources: 'Fuentes',
        newChat: 'Nueva conversación',
        errBusy: 'Demasiadas preguntas a la vez. Espera un minuto.',
        errGeneric: 'No pudimos enviar tu mensaje. Inténtalo de nuevo.',
        errLong: 'Esta conversación es demasiado larga. Empieza una nueva.',
        tabAsk: "Preguntar",
        tabDocs: "Docs",
        tabFaq: "FAQ",
        searchDocs: "Buscar en la documentación…",
        openHelpCentre: "Abrir el centro de ayuda",
        noResults: "No se encontró nada. Prueba otras palabras o pregunta al asistente.",
        faqEmpty: "Aún no hay preguntas frecuentes.",
        askInstead: "¿No lo encuentras?",
        askAssistant: "Preguntar al asistente",
        loading: "Cargando…",
        loadFailed: "No se pudo cargar. Inténtalo de nuevo.",
    },
    de: {
        launcher: 'Hilfe',
        title: 'Hilfe',
        close: 'Schließen',
        intro: 'Stellen Sie eine Frage zu {app}. Die Antworten stammen aus der Dokumentation von {app}.',
        placeholder: 'Ihre Frage…',
        send: 'Senden',
        thinking: 'Suche in der Dokumentation…',
        declined: 'Ich kann nur Fragen zu {app} beantworten, und in der Dokumentation habe ich dazu nichts gefunden.',
        failed: 'Beim Antworten ist etwas schiefgelaufen.',
        human: 'Mit einer Person sprechen',
        escalated:
            'Wir haben Ihre Unterhaltung an unser Team weitergegeben. Sie erhalten die Antwort per E-Mail, und sie erscheint auch hier.',
        agent: 'Support-Team',
        sources: 'Quellen',
        newChat: 'Neue Unterhaltung',
        errBusy: 'Zu viele Fragen auf einmal. Bitte warten Sie eine Minute.',
        errGeneric: 'Ihre Nachricht konnte nicht gesendet werden. Bitte versuchen Sie es erneut.',
        errLong: 'Diese Unterhaltung ist zu lang. Beginnen Sie eine neue.',
        tabAsk: "Fragen",
        tabDocs: "Doku",
        tabFaq: "FAQ",
        searchDocs: "Dokumentation durchsuchen…",
        openHelpCentre: "Hilfe-Center öffnen",
        noResults: "Nichts gefunden. Versuchen Sie andere Wörter oder fragen Sie den Assistenten.",
        faqEmpty: "Noch keine häufigen Fragen.",
        askInstead: "Nicht gefunden?",
        askAssistant: "Den Assistenten fragen",
        loading: "Wird geladen…",
        loadFailed: "Konnte nicht geladen werden. Bitte erneut versuchen.",
    },
};

export function resolveLocale(preferred?: string): Locale {
    for (const candidate of [preferred, document.documentElement.lang, navigator.language]) {
        const short = candidate?.slice(0, 2).toLowerCase();
        if (short && (SUPPORTED as readonly string[]).includes(short)) return short as Locale;
    }

    return 'en';
}

export function t(locale: Locale, key: MessageKey, app: string): string {
    return MESSAGES[locale][key].replace(/\{app\}/g, app);
}
