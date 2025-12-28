// Gestion du formulaire d'allaitement avec sauvegarde auto

const STORAGE_KEY = 'allaitement_form_data';
const STORAGE_TIMESTAMP_KEY = 'allaitement_form_timestamp';
const EXPIRATION_HOURS = 2; // Expiration après 2h d'inactivité

/**
 * Formate une date en format datetime-local
 */
function formatDateTime(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    return `${year}-${month}-${day}T${hours}:${minutes}`;
}

/**
 * Définit un champ à la date/heure actuelle
 */
function setNow(fieldId) {
    const now = new Date();
    const field = document.getElementById(fieldId);
    if (field) {
        field.value = formatDateTime(now);
        saveFormData(); // Sauvegarder automatiquement
    }
}

/**
 * Définit un champ à X minutes dans le passé
 */
function setMinus(fieldId, minutes) {
    const date = new Date();
    date.setMinutes(date.getMinutes() - minutes);
    const field = document.getElementById(fieldId);
    if (field) {
        field.value = formatDateTime(date);
        saveFormData(); // Sauvegarder automatiquement
    }
}

/**
 * Calcule et affiche la durée en temps réel
 */
function updateDuration() {
    const debut = document.getElementById('date_debut');
    const fin = document.getElementById('date_fin');
    const durationDisplay = document.getElementById('duration_display');
    
    if (!debut || !fin || !durationDisplay) return;
    
    if (debut.value && fin.value) {
        const start = new Date(debut.value);
        const end = new Date(fin.value);
        const diffMinutes = Math.round((end - start) / 1000 / 60);
        
        if (diffMinutes > 0) {
            durationDisplay.textContent = `Durée: ${diffMinutes} minutes`;
            durationDisplay.style.color = diffMinutes > 45 ? '#ffc107' : '#667eea';
            
            // Avertissement si durée anormale
            if (diffMinutes > 60) {
                durationDisplay.innerHTML = `⚠️ Durée: ${diffMinutes} minutes<br><small style="font-size: 12px;">Durée inhabituelle</small>`;
            }
        } else {
            durationDisplay.textContent = 'La fin doit être après le début';
            durationDisplay.style.color = '#dc3545';
        }
    } else {
        durationDisplay.textContent = '';
    }
}

/**
 * Sauvegarde les données du formulaire dans localStorage
 */
function saveFormData() {
    const debut = document.getElementById('date_debut');
    const fin = document.getElementById('date_fin');
    const seinGauche = document.getElementById('gauche');
    const seinDroit = document.getElementById('droit');
    
    if (!debut) return; // Pas de formulaire sur cette page
    
    const formData = {
        date_debut: debut.value || '',
        date_fin: fin.value || '',
        sein: seinGauche.checked ? 'gauche' : (seinDroit.checked ? 'droit' : '')
    };
    
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(formData));
        localStorage.setItem(STORAGE_TIMESTAMP_KEY, new Date().getTime().toString());
        
        // Afficher un indicateur visuel de sauvegarde
        showSaveIndicator();
    } catch (e) {
        console.error('Erreur lors de la sauvegarde:', e);
    }
}

/**
 * Restaure les données du formulaire depuis localStorage
 */
function restoreFormData() {
    try {
        const savedData = localStorage.getItem(STORAGE_KEY);
        const savedTimestamp = localStorage.getItem(STORAGE_TIMESTAMP_KEY);
        
        if (!savedData || !savedTimestamp) return false;
        
        // Vérifier si les données ne sont pas expirées
        const now = new Date().getTime();
        const savedTime = parseInt(savedTimestamp);
        const hoursDiff = (now - savedTime) / (1000 * 60 * 60);
        
        if (hoursDiff > EXPIRATION_HOURS) {
            clearFormData();
            return false;
        }
        
        const formData = JSON.parse(savedData);
        
        // Restaurer les valeurs
        const debut = document.getElementById('date_debut');
        const fin = document.getElementById('date_fin');
        const seinGauche = document.getElementById('gauche');
        const seinDroit = document.getElementById('droit');
        
        if (debut && formData.date_debut) {
            debut.value = formData.date_debut;
        }
        
        if (fin && formData.date_fin) {
            fin.value = formData.date_fin;
        }
        
        if (formData.sein === 'gauche' && seinGauche) {
            seinGauche.checked = true;
        } else if (formData.sein === 'droit' && seinDroit) {
            seinDroit.checked = true;
        }
        
        // Afficher un message de restauration
        showRestoreMessage(hoursDiff);
        
        // Mettre à jour l'affichage de la durée
        updateDuration();
        
        return true;
    } catch (e) {
        console.error('Erreur lors de la restauration:', e);
        return false;
    }
}

/**
 * Efface les données sauvegardées
 */
function clearFormData() {
    try {
        localStorage.removeItem(STORAGE_KEY);
        localStorage.removeItem(STORAGE_TIMESTAMP_KEY);
    } catch (e) {
        console.error('Erreur lors du nettoyage:', e);
    }
}

/**
 * Affiche un indicateur de sauvegarde automatique
 */
function showSaveIndicator() {
    const existingIndicator = document.getElementById('save-indicator');
    if (existingIndicator) {
        existingIndicator.remove();
    }
    
    const indicator = document.createElement('div');
    indicator.id = 'save-indicator';
    indicator.innerHTML = '💾 Sauvegardé';
    indicator.style.cssText = `
        position: fixed;
        bottom: 20px;
        right: 20px;
        background: #28a745;
        color: white;
        padding: 10px 20px;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        z-index: 9999;
        animation: slideIn 0.3s ease-out;
    `;
    
    document.body.appendChild(indicator);
    
    setTimeout(() => {
        indicator.style.animation = 'slideOut 0.3s ease-out';
        setTimeout(() => indicator.remove(), 300);
    }, 2000);
}

/**
 * Affiche un message de restauration
 */
function showRestoreMessage(hoursDiff) {
    const minutes = Math.round(hoursDiff * 60);
    const timeText = minutes < 60 
        ? `${minutes} minute${minutes > 1 ? 's' : ''}` 
        : `${Math.round(hoursDiff * 10) / 10} heure${hoursDiff > 1 ? 's' : ''}`;
    
    const message = document.createElement('div');
    message.className = 'message success';
    message.innerHTML = `
        <strong>📋 Données restaurées</strong><br>
        <small>Saisie commencée il y a ${timeText}</small>
        <button onclick="clearFormAndReload()" style="margin-left: 15px; padding: 5px 10px; background: white; color: #155724; border: 1px solid #155724; border-radius: 5px; cursor: pointer; font-size: 12px;">
            ✗ Nouvelle saisie
        </button>
    `;
    message.style.marginBottom = '20px';
    
    const form = document.querySelector('form');
    if (form) {
        form.parentNode.insertBefore(message, form);
    }
}

/**
 * Efface le formulaire et recharge la page
 */
function clearFormAndReload() {
    clearFormData();
    location.reload();
}

/**
 * Ajoute les animations CSS
 */
function addAnimations() {
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(style);
}

/**
 * Initialisation au chargement de la page
 */
document.addEventListener('DOMContentLoaded', function() {
    addAnimations();
    
    const confirmationBox = document.querySelector('.confirmation-box');
    const debut = document.getElementById('date_debut');
    const fin = document.getElementById('date_fin');
    
    // Si on est sur la page du formulaire
    if (debut) {
        // Tenter de restaurer les données
        const restored = restoreFormData();
        
        // Si pas de restauration et pas de confirmation, initialiser à maintenant
        if (!restored && !confirmationBox && !debut.value) {
            setNow('date_debut');
        }
        
        // Ajouter les écouteurs pour la sauvegarde automatique
        debut.addEventListener('change', function() {
            saveFormData();
            updateDuration();
        });
        
        if (fin) {
            fin.addEventListener('change', function() {
                saveFormData();
                updateDuration();
            });
        }
        
        // Sauvegarder quand on change de sein
        const seinGauche = document.getElementById('gauche');
        const seinDroit = document.getElementById('droit');
        
        if (seinGauche) {
            seinGauche.addEventListener('change', saveFormData);
        }
        
        if (seinDroit) {
            seinDroit.addEventListener('change', saveFormData);
        }
        
        // Nettoyer le localStorage après soumission réussie
        const successMessage = document.querySelector('.message.success');
        if (successMessage && successMessage.textContent.includes('enregistrée avec succès')) {
            clearFormData();
        }
    }
    
    // Mettre à jour le temps écoulé toutes les minutes
    const derniereInfo = document.querySelector('.derniere-tetee-info');
    if (derniereInfo) {
        setInterval(function() {
            location.reload();
        }, 60000); // Rafraîchir toutes les minutes
    }
});