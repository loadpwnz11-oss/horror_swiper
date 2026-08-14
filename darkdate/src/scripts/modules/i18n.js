/**
 * DarkDate — Internationalization Module
 * Поддержка: RU, EN, PT-BR, ZH-CN, ES, JA, DE, ID
 */

export class I18n {
    constructor() {
        this.currentLang = 'ru';
        this.translations = {};
        this.profilesData = null;
        this.supportedLangs = ['ru', 'en', 'pt-BR', 'zh-CN', 'es', 'ja', 'de', 'id'];
    }

    async load(lang) {
        if (!this.supportedLangs.includes(lang)) {
            console.warn(`[i18n] Unsupported language: ${lang}, falling back to 'ru'`);
            lang = 'ru';
        }

        try {
            // Загружаем основной файл локализации
            const response = await fetch(`src/data/i18n/${lang}.json`);
            this.translations = await response.json();
            
            // Загружаем profiles.json если ещё не загружен
            if (!this.profilesData) {
                const profilesResponse = await fetch('src/data/profiles.json');
                this.profilesData = await profilesResponse.json();
            }
            
            this.currentLang = lang;
            document.documentElement.lang = lang;
            this.applyToDOM();
        } catch (e) {
            console.error(`[i18n] Failed to load ${lang}:`, e);
        }
    }

    /** Получить перевод по ключу (поддерживает вложенность: 'modal.title') */
    t(key) {
        return key.split('.').reduce((obj, part) => obj?.[part], this.translations) || key;
    }

    /** Получить перевод для профиля по ID и полю */
    translateProfileField(profileId, field, lang = null) {
        const targetLang = lang || this.currentLang;
        
        // Ищем профиль в profilesData
        const profile = this.profilesData?.profiles?.find(p => p.id === profileId);
        if (!profile || !profile.translations) return null;
        
        const translation = profile.translations[targetLang];
        if (!translation) return null;
        
        // Обработка тегов (массив)
        if (field.startsWith('tags[')) {
            const indexMatch = field.match(/tags\[(\d+)\]/);
            if (indexMatch) {
                const index = parseInt(indexMatch[1], 10);
                const tagsArray = translation.tags;
                if (Array.isArray(tagsArray) && tagsArray[index] !== undefined) {
                    return tagsArray[index];
                }
            }
            return null;
        }
        
        // Обработка обычных полей (name, bio)
        return translation[field] || null;
    }

    /** Применить переводы ко всем элементам с data-i18n */
    applyToDOM() {
        document.querySelectorAll('[data-i18n]').forEach(el => {
            const key = el.getAttribute('data-i18n');
            const translation = this.t(key);
            if (translation !== key) {
                el.textContent = translation;
            }
        });
        
        // Применяем перевод к карточкам профилей (динамически генерируемые элементы)
        this.applyToProfiles();
    }
    
    /** Применить перевод к полям профилей */
    applyToProfiles() {
        // Переводим все карточки профилей на странице
        document.querySelectorAll('.profile-card').forEach(card => {
            const profileId = card.dataset.profileId;
            if (!profileId) return;
            
            // Находим профиль в profilesData
            const profile = this.profilesData?.profiles?.find(p => p.id === profileId);
            if (!profile) return;
            
            const translation = profile.translations?.[this.currentLang];
            if (!translation) return;
            
            // Перевод имени с возрастом
            const nameEl = card.querySelector('.card-name');
            if (nameEl) {
                const ageDisplay = profile.age !== null ? `, ${profile.age}` : '';
                nameEl.textContent = translation.name + ageDisplay;
            }
            
            // Перевод био
            const bioEl = card.querySelector('.card-bio');
            if (bioEl) {
                bioEl.textContent = translation.bio;
            }
            
            // Перевод тегов
            const tagContainer = card.querySelector('.card-tags');
            if (tagContainer && Array.isArray(translation.tags)) {
                tagContainer.innerHTML = translation.tags.map(tag => 
                    `<span class="card-tag">${tag}</span>`
                ).join('');
            }
        });
        
        // Переводим штампы LIKE/NOPE
        document.querySelectorAll('.stamp-like').forEach(el => {
            el.textContent = this.t('swipe.like');
        });
        document.querySelectorAll('.stamp-nope').forEach(el => {
            el.textContent = this.t('swipe.nope');
        });
    }
    
    /** Получить перевод профиля по ID */
    getProfileTranslation(profileId) {
        const profile = this.profilesData?.profiles?.find(p => p.id === profileId);
        if (!profile || !profile.translations) return null;
        return profile.translations[this.currentLang] || null;
    }
    
    /** Получить все поддерживаемые языки с метаданными */
    getSupportedLanguages() {
        return {
            'ru': { name: 'Русский', flag: '🇷🇺' },
            'en': { name: 'English', flag: '🇬🇧' },
            'pt-BR': { name: 'Português (BR)', flag: '🇧🇷' },
            'zh-CN': { name: '中文', flag: '🇨🇳' },
            'es': { name: 'Español', flag: '🇪🇸' },
            'ja': { name: '日本語', flag: '🇯🇵' },
            'de': { name: 'Deutsch', flag: '🇩🇪' },
            'id': { name: 'Bahasa Indonesia', flag: '🇮🇩' }
        };
    }

    /** Смена языка */
    async setLanguage(lang) {
        await this.load(lang);
        localStorage.setItem('darkdate_lang', lang);
    }

    /** Получить сохранённый язык */
    getSavedLanguage() {
        return localStorage.getItem('darkdate_lang') || 'ru';
    }

    /** Получить текущий язык */
    getCurrentLang() {
        return this.currentLang;
    }
}