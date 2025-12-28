// Gestion du formulaire d'allaitement

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
        } else {
            durationDisplay.textContent = 'La fin doit être après le début';
            durationDisplay.style.color = '#dc3545';
        }
    } else {
        durationDisplay.textContent = '';
    }
}

/**
 * Initialisation au chargement de la page
 */
document.addEventListener('DOMContentLoaded', function() {
    // Définir la date de début à maintenant si le formulaire n'est pas en mode confirmation
    const confirmationBox = document.querySelector('.confirmation-box');
    if (!confirmationBox) {
        setNow('date_debut');
    }
    
    // Ajouter les écouteurs pour le calcul de durée
    const debut = document.getElementById('date_debut');
    const fin = document.getElementById('date_fin');
    
    if (debut) {
        debut.addEventListener('change', updateDuration);
    }
    
    if (fin) {
        fin.addEventListener('change', updateDuration);
    }
    
    // Mettre à jour le temps écoulé toutes les minutes
    const derniereInfo = document.querySelector('.derniere-tetee-info');
    if (derniereInfo) {
        setInterval(function() {
            location.reload();
        }, 60000); // Rafraîchir toutes les minutes
    }
});