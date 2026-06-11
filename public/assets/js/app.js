document.addEventListener('DOMContentLoaded', () => {
    const body = document.body;
    const modal = document.getElementById('authModal');
    const requiresAuth = document.querySelectorAll('.requires-auth');

    const openModal = () => {
        if (!modal) {
            window.location.href = '/login';
            return;
        }

        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
    };

    const closeModal = () => {
        if (!modal) {
            return;
        }

        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
    };

    requiresAuth.forEach((item) => {
        item.addEventListener('click', (event) => {
            if (body.dataset.auth === 'guest') {
                event.preventDefault();
                openModal();
            }
        });
    });

    document.querySelectorAll('[data-auth-close]').forEach((item) => {
        item.addEventListener('click', closeModal);
    });

    const profileEditModal = document.getElementById('profileEditModal');

    const openProfileEditModal = () => {
        if (!profileEditModal) {
            return;
        }

        body.classList.add('modal-lock');
        profileEditModal.classList.add('is-open');
        profileEditModal.setAttribute('aria-hidden', 'false');
        profileEditModal.querySelector('input[name="name"]')?.focus();
    };

    const closeProfileEditModal = () => {
        if (!profileEditModal) {
            return;
        }

        profileEditModal.classList.remove('is-open');
        profileEditModal.setAttribute('aria-hidden', 'true');
        body.classList.remove('modal-lock');
    };

    document.querySelectorAll('[data-profile-edit-open]').forEach((item) => {
        item.addEventListener('click', openProfileEditModal);
    });

    document.querySelectorAll('[data-profile-edit-close]').forEach((item) => {
        item.addEventListener('click', closeProfileEditModal);
    });

    document.querySelectorAll('[data-avatar-input]').forEach((input) => {
        input.addEventListener('change', () => {
            if (input.files && input.files.length > 0) {
                input.closest('[data-avatar-form]')?.submit();
            }
        });
    });

    const truckSelectModal = document.getElementById('truckSelectModal');

    const openTruckSelectModal = () => {
        if (!truckSelectModal) {
            return;
        }

        body.classList.add('modal-lock');
        truckSelectModal.classList.add('is-open');
        truckSelectModal.setAttribute('aria-hidden', 'false');
        truckSelectModal.querySelector('input[name="vehicle_model"]')?.focus();
    };

    const closeTruckSelectModal = () => {
        if (!truckSelectModal) {
            return;
        }

        truckSelectModal.classList.remove('is-open');
        truckSelectModal.setAttribute('aria-hidden', 'true');
        body.classList.remove('modal-lock');
    };

    document.querySelectorAll('[data-truck-select-open]').forEach((item) => {
        item.addEventListener('click', openTruckSelectModal);
    });

    document.querySelectorAll('[data-truck-select-close]').forEach((item) => {
        item.addEventListener('click', closeTruckSelectModal);
    });

    const closeRouteDetailModals = () => {
        document.querySelectorAll('[data-route-detail-modal].is-open').forEach((modalItem) => {
            modalItem.classList.remove('is-open');
            modalItem.setAttribute('aria-hidden', 'true');
        });
        body.classList.remove('modal-lock');
    };

    document.querySelectorAll('[data-route-detail-open]').forEach((item) => {
        item.addEventListener('click', () => {
            const routeModal = document.getElementById(`routeDetailModal-${item.dataset.routeDetailOpen}`);

            if (!routeModal) {
                return;
            }

            body.classList.add('modal-lock');
            routeModal.classList.add('is-open');
            routeModal.setAttribute('aria-hidden', 'false');
        });
    });

    document.querySelectorAll('[data-route-detail-close]').forEach((item) => {
        item.addEventListener('click', closeRouteDetailModals);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeModal();
            closeProfileEditModal();
            closeTruckSelectModal();
            closeRouteDetailModals();
        }
    });

    const truckModel = document.querySelector('[data-truck-model]');

    const fillTruckSpecs = () => {
        if (!truckModel) {
            return;
        }

        const option = truckModel.selectedOptions[0];

        if (!option) {
            return;
        }

        const fields = {
            type: document.querySelector('[data-vehicle-type-input]'),
            fuel: document.querySelector('[data-fuel-type-input]'),
            allowedFuel: document.querySelector('[data-allowed-fuel-input]'),
            tank: document.querySelector('[data-tank-input]'),
            consumption: document.querySelector('[data-consumption-input]'),
            speed: document.querySelector('[data-speed-input]'),
            restrictions: document.querySelector('[data-restrictions-input]'),
            curbWeight: document.querySelector('[data-curb-weight-input]'),
        };

        if (fields.type) {
            fields.type.value = option.dataset.type || '';
        }

        if (fields.fuel) {
            fields.fuel.value = option.dataset.fuel || '';
        }

        if (fields.allowedFuel) {
            fields.allowedFuel.value = option.dataset.allowedFuel || '';
        }

        if (fields.tank) {
            fields.tank.value = option.dataset.tank || '';
        }

        if (fields.consumption) {
            fields.consumption.value = option.dataset.consumption || '';
        }

        if (fields.speed) {
            fields.speed.value = option.dataset.speed || '';
        }

        if (fields.restrictions) {
            fields.restrictions.value = option.dataset.restrictions || '';
        }

        if (fields.curbWeight) {
            fields.curbWeight.value = option.dataset.curbWeight || '';
        }
    };

    if (truckModel) {
        truckModel.addEventListener('change', fillTruckSpecs);
        fillTruckSpecs();

        const form = document.getElementById('route-calculation-form');

        if (form) {
            form.addEventListener('reset', () => {
                window.setTimeout(fillTruckSpecs, 0);
            });
        }
    }

    const updateTruckPreview = (choice) => {
        const fields = {
            type: document.querySelector('[data-vehicle-type-input]'),
            fuel: document.querySelector('[data-fuel-type-input]'),
            allowedFuel: document.querySelector('[data-allowed-fuel-input]'),
            tank: document.querySelector('[data-tank-input]'),
            consumption: document.querySelector('[data-consumption-input]'),
            speed: document.querySelector('[data-speed-input]'),
            curbWeight: document.querySelector('[data-curb-weight-input]'),
            restrictions: document.querySelector('[data-restrictions-input]'),
        };

        if (fields.type) fields.type.value = choice.dataset.type || '';
        if (fields.fuel) fields.fuel.value = choice.dataset.fuel || '';
        if (fields.allowedFuel) fields.allowedFuel.value = choice.dataset.allowedFuel || '';
        if (fields.tank) fields.tank.value = choice.dataset.tank || '';
        if (fields.consumption) fields.consumption.value = choice.dataset.consumption || '';
        if (fields.speed) fields.speed.value = choice.dataset.speed || '';
        if (fields.curbWeight) fields.curbWeight.value = choice.dataset.curbWeight || '';
        if (fields.restrictions) fields.restrictions.value = choice.dataset.restrictions || '';

        const image = document.querySelector('[data-truck-preview-image]');
        const brand = document.querySelector('[data-truck-preview-brand]');
        const model = document.querySelector('[data-truck-preview-model]');
        const main = document.querySelector('[data-truck-preview-main]');
        const fuel = document.querySelector('[data-truck-preview-fuel]');
        const allowed = document.querySelector('[data-truck-preview-allowed]');
        const speed = document.querySelector('[data-truck-preview-speed]');
        const weight = document.querySelector('[data-truck-preview-weight]');
        const restrictions = document.querySelector('[data-truck-preview-restrictions]');

        if (image && choice.dataset.image) image.src = choice.dataset.image;
        if (brand) brand.textContent = choice.dataset.brand || '';
        if (model) model.textContent = choice.value;
        if (main) {
            main.textContent = `${choice.dataset.type || ''}. Бак ${choice.dataset.tank || '-'} л, расход ${choice.dataset.consumption || '-'} л / 100 км.`;
        }
        if (fuel) fuel.textContent = choice.dataset.fuel || '';
        if (allowed) allowed.textContent = choice.dataset.allowedFuel || '';
        if (speed) speed.textContent = `${choice.dataset.speed || '-'} км/ч`;
        if (weight) weight.textContent = `${choice.dataset.curbWeight || '-'} т`;
        if (restrictions) restrictions.textContent = choice.dataset.restrictions || '';
    };

    const truckChoices = document.querySelectorAll('[data-truck-choice]');

    truckChoices.forEach((choice) => {
        choice.addEventListener('change', () => {
            truckChoices.forEach((item) => {
                item.closest('.truck-model-option')?.classList.toggle('is-selected', item.checked);
            });
            updateTruckPreview(choice);
        });

        if (choice.checked) {
            updateTruckPreview(choice);
        }
    });

    const newsFilterForm = document.querySelector('[data-news-filter-form]');

    if (newsFilterForm) {
        const filterCards = () => {
            const highway = String(newsFilterForm.elements.highway?.value || '');
            const query = String(newsFilterForm.elements.query?.value || '').trim().toLowerCase();
            const type = String(newsFilterForm.elements.type?.value || '');
            const status = String(newsFilterForm.elements.status?.value || '');
            let visibleResultCards = 0;

            document.querySelectorAll('[data-event-card]').forEach((card) => {
                const haystack = String(card.dataset.search || '').toLowerCase();
                const cardHighway = card.dataset.highway || '';
                const cardType = card.dataset.type || '';
                const cardStatus = card.dataset.status || '';
                const visible = (!highway || cardHighway === highway)
                    && (!query || haystack.includes(query))
                    && (!type || cardType === type)
                    && (!status || cardStatus === status);

                card.hidden = !visible;

                if (visible && card.closest('[data-news-results]')) {
                    visibleResultCards += 1;
                }
            });

            document.querySelectorAll('[data-news-empty]').forEach((item) => {
                item.hidden = visibleResultCards > 0;
            });
        };

        newsFilterForm.addEventListener('submit', (event) => {
            event.preventDefault();
            filterCards();
            document.querySelector('[data-news-results]')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });

        newsFilterForm.addEventListener('reset', () => {
            window.setTimeout(() => {
                document.querySelectorAll('[data-event-card]').forEach((card) => {
                    card.hidden = false;
                });
                document.querySelectorAll('[data-news-empty]').forEach((item) => {
                    item.hidden = true;
                });
            }, 0);
        });
    }

    const routeForm = document.getElementById('route-calculation-form');
    const restModal = document.getElementById('restPlanningModal');
    const routeDataNode = document.getElementById('routeNavigatorData');
    const routeData = routeDataNode ? JSON.parse(routeDataNode.textContent || '{}') : { routes: [], restObjects: [] };

    const normalize = (value) => String(value || '').trim().toLowerCase();

    const matchRoute = () => {
        if (!routeForm) {
            return null;
        }

        const origin = normalize(routeForm.elements.origin?.value);
        const destination = normalize(routeForm.elements.destination?.value);

        return (routeData.routes || []).reduce((found, route) => {
            if (found) {
                return found;
            }

            const direct = normalize(route.origin) === origin && normalize(route.destination) === destination;
            const reverse = normalize(route.origin) === destination && normalize(route.destination) === origin;

            if (!direct && !reverse) {
                return null;
            }

            return { ...route, reverse };
        }, null);
    };

    const updateRouteDefaults = () => {
        const route = matchRoute();
        const distanceInput = document.querySelector('[data-route-distance]');
        const viaInput = document.querySelector('[data-route-via]');

        if (!route || !distanceInput || !viaInput) {
            return null;
        }

        distanceInput.value = route.distance_km || '';
        viaInput.value = route.via_point || '';

        return route;
    };

    const formatDuration = (minutes) => {
        const hours = Math.floor(minutes / 60);
        const restMinutes = minutes % 60;

        return `${hours} ч ${restMinutes} мин`;
    };

    const formatTime = (date) => {
        return date.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
    };

    const restCandidates = () => {
        const route = updateRouteDefaults();

        if (!routeForm || !route) {
            return [];
        }

        const speed = Number(document.querySelector('[data-speed-input]')?.value || 80);
        const startValue = routeForm.elements.start_time?.value;
        const startDate = startValue ? new Date(startValue) : new Date();
        const distance = Number(route.distance_km || 0);

        return (routeData.restObjects || [])
            .map((object) => {
                const objectKm = Number(object.km_marker || 0);
                const distanceFromStart = route.reverse ? distance - objectKm : objectKm;

                return { ...object, distance_from_start: distanceFromStart };
            })
            .filter((object) => object.highway === route.highway)
            .filter((object) => object.distance_from_start > 90 && object.distance_from_start < distance - 40)
            .filter((object) => object.type !== 'АЗС' || object.has_truck_parking)
            .sort((a, b) => a.distance_from_start - b.distance_from_start)
            .slice(0, 5)
            .map((object) => {
                const minutes = Math.ceil((object.distance_from_start / speed) * 60);
                const eta = new Date(startDate.getTime() + minutes * 60000);

                return { ...object, minutes, eta };
            });
    };

    const openRestModal = () => {
        if (!restModal) {
            return;
        }

        const list = restModal.querySelector('[data-rest-list]');

        if (list) {
            list.hidden = true;
        }

        body.classList.add('modal-lock');
        restModal.classList.add('is-open');
        restModal.setAttribute('aria-hidden', 'false');
    };

    const closeRestModal = () => {
        if (!restModal) {
            return;
        }

        restModal.classList.remove('is-open');
        restModal.setAttribute('aria-hidden', 'true');
        body.classList.remove('modal-lock');
    };

    const renderRestCandidates = () => {
        if (!restModal) {
            return;
        }

        const list = restModal.querySelector('[data-rest-list]');
        const choices = restModal.querySelector('[data-rest-choices]');
        const candidates = restCandidates();

        if (!list || !choices) {
            return;
        }

        list.hidden = false;

        if (candidates.length === 0) {
            choices.innerHTML = `
                <div class="card dark-card">
                    <h3>Подходящих мест не найдено</h3>
                    <p>Для этого направления пока нет точек отдыха в базе. Можно построить маршрут без остановки.</p>
                </div>
            `;
            return;
        }

        choices.innerHTML = candidates.map((candidate, index) => `
            <label class="rest-choice ${index === 0 ? 'is-selected' : ''}">
                <input class="rest-choice__input" type="radio" name="rest_choice" value="${candidate.id}" ${index === 0 ? 'checked' : ''}>
                <span class="rest-choice__marker" aria-hidden="true"></span>
                <span class="rest-choice__content">
                    <span class="rest-choice__type">${candidate.type}</span>
                    <strong>${candidate.name}</strong>
                    <span>${candidate.location || candidate.highway}, ${Math.round(candidate.distance_from_start)} км от старта</span>
                    <span>Будете рядом через ${formatDuration(candidate.minutes)}, примерно в ${formatTime(candidate.eta)}. Отъезд от маршрута: ${candidate.detour_km || 0} км.</span>
                </span>
            </label>
        `).join('');

        choices.querySelectorAll('input[name="rest_choice"]').forEach((input) => {
            input.addEventListener('change', () => {
                choices.querySelectorAll('.rest-choice').forEach((choice) => {
                    const choiceInput = choice.querySelector('input[name="rest_choice"]');
                    choice.classList.toggle('is-selected', Boolean(choiceInput?.checked));
                });
            });
        });
    };

    const submitRoute = (includeRest) => {
        if (!routeForm) {
            return;
        }

        const includeRestInput = document.querySelector('[data-include-rest]');
        const selectedRestInput = document.querySelector('[data-selected-rest]');

        if (includeRestInput) {
            includeRestInput.value = includeRest ? '1' : '0';
        }

        if (selectedRestInput) {
            selectedRestInput.value = '';
        }

        if (includeRest && selectedRestInput) {
            const checked = restModal?.querySelector('input[name="rest_choice"]:checked');
            selectedRestInput.value = checked?.value || '';
        }

        routeForm.dataset.restDecision = 'ready';
        closeRestModal();
        routeForm.requestSubmit();
    };

    if (routeForm) {
        ['origin', 'destination'].forEach((field) => {
            routeForm.elements[field]?.addEventListener('change', updateRouteDefaults);
        });

        updateRouteDefaults();

        routeForm.addEventListener('submit', (event) => {
            if (body.dataset.auth === 'guest') {
                event.preventDefault();
                openModal();
                return;
            }

            updateRouteDefaults();

            if (routeForm.dataset.restDecision === 'ready') {
                return;
            }

            event.preventDefault();
            openRestModal();
        });
    }

    restModal?.querySelectorAll('[data-rest-close]').forEach((item) => {
        item.addEventListener('click', closeRestModal);
    });

    restModal?.querySelector('[data-rest-show]')?.addEventListener('click', renderRestCandidates);

    restModal?.querySelectorAll('[data-rest-skip]').forEach((item) => {
        item.addEventListener('click', () => submitRoute(false));
    });

    restModal?.querySelector('[data-rest-submit]')?.addEventListener('click', () => submitRoute(true));

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeRestModal();
        }
    });

});
