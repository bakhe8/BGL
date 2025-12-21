/**
 * AI-First Experiment - Interactive Logic
 */

// Experiment configuration
window.EXPERIMENT_NAME = 'ai-first-v1';

// Metrics tracking
const Metrics = {
    startTime: Date.now(),
    events: [],

    track(eventName, data = {}) {
        this.events.push({
            event: eventName,
            timestamp: Date.now() - this.startTime,
            data: data
        });
        console.log(`[Metrics] ${eventName}`, data);
    },

    save() {
        const metrics = {
            experiment: window.EXPERIMENT_NAME,
            duration: Date.now() - this.startTime,
            events: this.events,
            userAgent: navigator.userAgent
        };

        // Save to localStorage
        const key = `lab_metrics_${window.EXPERIMENT_NAME}_${Date.now()}`;
        localStorage.setItem(key, JSON.stringify(metrics));
        console.log('[Metrics] Saved:', key);
    }
};

// Track page load
Metrics.track('page_loaded');

// Decision Management
const DecisionFlow = {
    selectedDecision: null,
    manualMode: false,

    init() {
        // AI quick action
        const quickApproveBtn = document.getElementById('quick-approve');
        if (quickApproveBtn) {
            quickApproveBtn.addEventListener('click', () => this.quickApprove());
        }

        // Manual mode toggle
        const manualModeBtn = document.getElementById('manual-mode');
        if (manualModeBtn) {
            manualModeBtn.addEventListener('click', () => this.toggleManualMode());
        }

        // Decision cards
        const cards = document.querySelectorAll('.decision-card');
        cards.forEach(card => {
            card.addEventListener('click', () => this.selectDecision(card));
        });

        // Save button
        const saveBtn = document.getElementById('save-decision');
        if (saveBtn) {
            saveBtn.addEventListener('click', () => this.saveDecision());
        }

        // Context toggles
        this.initContextToggles();
    },

    quickApprove() {
        Metrics.track('quick_approve_clicked');

        this.showSimulationNotice('تمت المحاكاة: الموافقة السريعة من توصية AI');

        setTimeout(() => {
            Metrics.track('decision_completed', {
                decision: 'approve',
                source: 'ai_quick',
                timeToDecision: Date.now() - Metrics.startTime
            });
            Metrics.save();

            alert('✅ تم! في النظام الحقيقي، سيتم حفظ القرار الآن.\n\n📊 المقا يسات:\n- الوقت: ' +
                Math.round((Date.now() - Metrics.startTime) / 1000) + ' ثانية\n' +
                '- النقرات: 1\n- المصدر: AI Quick Approve');
        }, 500);
    },

    toggleManualMode() {
        this.manualMode = !this.manualMode;
        const section = document.getElementById('decision-section');

        if (this.manualMode) {
            section.classList.add('expanded');
            Metrics.track('manual_mode_opened');
        } else {
            section.classList.remove('expanded');
            Metrics.track('manual_mode_closed');
        }
    },

    selectDecision(card) {
        // Remove previous selection
        document.querySelectorAll('.decision-card').forEach(c => {
            c.classList.remove('selected');
        });

        // Select new
        card.classList.add('selected');
        this.selectedDecision = card.dataset.decision;

        Metrics.track('decision_selected', {
            decision: this.selectedDecision
        });
    },

    saveDecision() {
        if (!this.selectedDecision) {
            alert('⚠️ يرجى اختيار قرار أولاً');
            return;
        }

        Metrics.track('save_clicked', {
            decision: this.selectedDecision
        });

        this.showSimulationNotice(`تمت المحاكاة: حفظ القرار (${this.selectedDecision})`);

        setTimeout(() => {
            Metrics.track('decision_completed', {
                decision: this.selectedDecision,
                source: 'manual',
                timeToDecision: Date.now() - Metrics.startTime
            });
            Metrics.save();

            alert('✅ تم! في النظام الحقيقي، سيتم حفظ القرار الآن.\n\n📊 المقاييس:\n- الوقت: ' +
                Math.round((Date.now() - Metrics.startTime) / 1000) + ' ثانية\n' +
                '- القرار: ' + this.selectedDecision + '\n' +
                '- المصدر: Manual Selection');
        }, 500);
    },

    initContextToggles() {
        const toggles = document.querySelectorAll('.context-toggle');
        toggles.forEach(toggle => {
            toggle.addEventListener('click', () => {
                const target = toggle.dataset.target;
                const drawer = document.getElementById(target);

                if (drawer) {
                    const isExpanded = drawer.classList.contains('expanded');
                    drawer.classList.toggle('expanded');

                    Metrics.track(isExpanded ? 'context_closed' : 'context_opened', {
                        context: target
                    });
                }
            });
        });
    },

    showSimulationNotice(message) {
        const notice = document.getElementById('simulation-notice');
        if (notice) {
            notice.textContent = `🧪 ${message}`;
            notice.classList.add('show');

            setTimeout(() => {
                notice.classList.remove('show');
            }, 3000);
        }
    }
};

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    DecisionFlow.init();
    Metrics.track('dom_ready');
});

// Track page unload
window.addEventListener('beforeunload', () => {
    Metrics.track('page_unload');
    Metrics.save();
});
