<script setup>
import { usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import SvgBcLogo from './SvgBcLogo.vue'

// Mirrors the B.C. Design System footer used by bcgov/pdex. Ministry details come from Maintenance > Utils.
const settings = computed(() => usePage().props.settings || {})
const ministryName = computed(() => settings.value.ministry_name || 'Ministry of Post-Secondary Education and Future Skills')
const privacyEmail = computed(() => settings.value.privacy_email || 'PSFS.InformationPrivacy@gov.bc.ca')
const links = [
    { href: 'https://www2.gov.bc.ca/gov/content/home', text: 'Home' },
    { href: 'https://www2.gov.bc.ca/gov/content?id=3C4F47288DFB454987435AB5EFEFBB7F', text: 'About gov.bc.ca' },
    { href: 'https://www2.gov.bc.ca/gov/content?id=79F93E018712422FBC8E674A67A70535', text: 'Disclaimer' },
    { href: 'https://www2.gov.bc.ca/gov/content?id=9E890E16955E4FF4BF3B0E07B4722932', text: 'Privacy' },
    { href: 'https://www2.gov.bc.ca/gov/content?id=E08E79740F9C41B9B0C484685CC5E412', text: 'Accessibility' },
    { href: 'https://www2.gov.bc.ca/gov/content?id=1AAACC9C65754E4D89A118B875E0FBDA', text: 'Copyright' },
    { href: 'https://www2.gov.bc.ca/gov/content?id=6A77C17D0CCB48F897F8598CCC019111', text: 'Contact us' },
]
const copyright = `© ${new Date().getUTCFullYear()} Government of British Columbia.`
</script>

<template>
    <footer class="bcds-footer">
        <div class="bcds-footer--acknowledgement">
            <div class="bcds-footer--acknowledgement-text">
                <p>
                    Your personal information is collected, used and disclosed by the {{ ministryName }}
                    under the Freedom of
                    Information and Protection of Privacy Act (FOIPPA) sections 26(c) and
                    26(e). The information you provide will be shared with the program
                    administration you choose to apply to through this portal. It will be used
                    for the purposes of confirming your identity and program eligibility,
                    administering financial assistance, and supporting program planning and
                    evaluation. If you have any questions about the collection of this
                    information, please contact: Ministry Privacy Officer,
                    {{ privacyEmail }}
                </p>
                <br />
                <p>
                    The B.C. Public Service acknowledges the territories of First Nations
                    around B.C. and is grateful to carry out our work on these lands. We
                    acknowledge the rights, interests, priorities, and concerns of all
                    Indigenous Peoples — First Nations, Métis, and Inuit — respecting and
                    acknowledging their distinct cultures, histories, rights, laws, and
                    governments.
                </p>
            </div>
        </div>
        <div class="bcds-footer--container">
            <div class="bcds-footer--container-content">
                <div class="bcds-footer--logo-links">
                    <div class="bcds-footer--logo">
                        <SvgBcLogo id="bcgov-logo-footer" />
                        <p>
                            We can help in over 220 languages and through other accessible options.
                            <a href="https://www2.gov.bc.ca/gov/content?id=6A77C17D0CCB48F897F8598CCC019111">Call, email or text us</a>,
                            or <a href="https://www2.gov.bc.ca/gov/content?id=FBC4210F6BC047A5884198F543C97D53">find a service centre</a>
                        </p>
                    </div>
                    <nav class="bcds-footer--links" aria-labelledby="bcds-footer-more-info">
                        <h2 id="bcds-footer-more-info" class="bcds-footer--links-title">More Info</h2>
                        <ul>
                            <li v-for="link in links" :key="link.href"><a :href="link.href">{{ link.text }}</a></li>
                        </ul>
                    </nav>
                </div>
                <hr />
                <p class="bcds-footer--copyright">{{ copyright }}</p>
            </div>
        </div>
    </footer>
</template>

<style scoped>
.bcds-footer {
    display: flex;
    flex-direction: column;
    align-items: stretch;
    width: 100%;
    background-color: rgb(250, 249, 248);
}

.bcds-footer--acknowledgement {
    background-color: rgb(41, 41, 41);
    color: rgb(250, 249, 248);
    font-size: 14px;
    line-height: 21px;
    border-top: 4px solid rgb(252, 186, 25);
    border-bottom: 4px solid rgb(252, 186, 25);
    padding: 32px 16px;
}

.bcds-footer--acknowledgement-text {
    max-width: 1120px;
    margin: auto;
}

.bcds-footer--acknowledgement-text p {
    margin: 0;
}

.bcds-footer--container {
    max-width: 1120px;
    width: 100%;
    margin: auto;
    padding: 32px;
    font-size: 14px;
    line-height: 21px;
    color: rgb(70, 67, 65);
}

@media (min-width: 992px) {
    .bcds-footer--container {
        padding-top: 56px;
        padding-bottom: 32px;
    }
}

.bcds-footer--container-content {
    display: flex;
    flex-direction: column;
    gap: 24px;
}

.bcds-footer--logo-links {
    display: flex;
    flex-direction: row;
    flex-wrap: nowrap;
    align-items: flex-start;
    justify-content: space-between;
    column-gap: 48px;
    row-gap: 24px;
}

.bcds-footer--logo {
    display: flex;
    flex-direction: column;
    gap: 24px;
    min-width: 324px;
    max-width: 576px;
    flex: 1;
}

#bcgov-logo-footer {
    width: 146px;
    min-width: 146px;
}

.bcds-footer--logo p,
.bcds-footer--copyright {
    margin: 0;
    color: rgb(70, 67, 65);
}

.bcds-footer--logo a {
    color: rgb(70, 67, 65);
    text-decoration: underline;
}

.bcds-footer--logo a:hover {
    text-decoration: none;
}

.bcds-footer--links {
    min-width: 320px;
}

.bcds-footer--links-title {
    margin: 0 0 16px;
    font-size: 14px;
    font-weight: bold;
    line-height: 21px;
    text-transform: uppercase;
    color: rgb(70, 67, 65);
}

.bcds-footer--links ul {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    column-gap: 24px;
    row-gap: 8px;
    list-style: none;
    margin: 0;
    padding: 0;
}

.bcds-footer--links a {
    color: rgb(70, 67, 65);
    text-decoration: none;
}

.bcds-footer--links a:hover {
    text-decoration: underline;
}

.bcds-footer hr {
    height: 1px;
    margin: 0;
    border: none;
    background-color: rgb(209, 209, 209);
}

@media (max-width: 991px) {
    .bcds-footer--logo-links {
        flex-wrap: wrap;
    }

    .bcds-footer--logo {
        max-width: 324px;
    }
}

@media (max-width: 575px) {
    .bcds-footer--acknowledgement {
        padding: 24px 16px;
    }

    .bcds-footer--container {
        padding: 16px;
    }

    .bcds-footer--container-content,
    .bcds-footer--logo {
        gap: 16px;
    }

    .bcds-footer--logo {
        min-width: 0;
    }

    .bcds-footer--links {
        min-width: 0;
    }
}
</style>
