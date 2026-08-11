import { defineStore } from 'pinia';

export interface PublicSettings {
  site_name: string;
  site_logo: string | null;
  site_favicon: string | null;
  logo_light: string | null;
  logo_dark: string | null;
  favicon: string | null;
  phone?: string | null;
  email?: string | null;
  address?: string | null;
  support_hours?: string | null;
  facebook?: string | null;
  instagram?: string | null;
  twitter?: string | null;
  youtube?: string | null;
  whatsapp_number?: string | null;
  contact_info_phone?: string | null;
  contact_info_email?: string | null;
  guarantee_text?: string | null;
  currency: string;
  currency_symbol: string;
  theme_color: string;
  maintenance_mode: boolean;
  announcement_bar_enabled: boolean;
  announcement_bar_text: string | null;
  languages: string[];
}

export const useSettingsStore = defineStore('settings', () => {
  const settings = ref<PublicSettings>({
    site_name: 'LaraShop',
    site_logo: null,
    site_favicon: null,
    logo_light: null,
    logo_dark: null,
    favicon: null,
    phone: '+880 1700-000000',
    email: 'support@larashop.com',
    address: 'Dhaka, Bangladesh',
    support_hours: 'Sat - Thu: 9:00 AM - 6:00 PM',
    facebook: 'https://facebook.com',
    instagram: 'https://instagram.com',
    twitter: 'https://twitter.com',
    youtube: 'https://youtube.com',
    whatsapp_number: '+8801700000000',
    guarantee_text: '100% Genuine Guaranteed',
    currency: 'BDT',
    currency_symbol: '৳',
    theme_color: '#4f46e5',
    maintenance_mode: false,
    announcement_bar_enabled: true,
    announcement_bar_text: '⚡ Free Shipping on orders over ৳2,000! Use code FREESHIP',
    languages: ['EN', 'BN'],
  });

  const isLoading = ref(false);

  function cleanString(val: any): string {
    if (!val) return '';
    let str = String(val).trim();
    if ((str.startsWith('"') && str.endsWith('"')) || (str.startsWith("'") && str.endsWith("'"))) {
      str = str.slice(1, -1);
    }
    return str;
  }

  function parseBool(val: any): boolean {
    if (val === true || val === 1 || val === '1' || val === 'true' || val === 'enabled' || val === 'on') return true;
    return false;
  }

  async function fetchSettings() {
    const { $api } = useNuxtApp();
    isLoading.value = true;
    try {
      const response = await $api.get('/settings/public');
      const data = response.data?.data || response.data;
      if (data && typeof data === 'object') {
        const rawMaint = data.maintenance_mode !== undefined ? data.maintenance_mode : data['general.maintenance_mode'];
        const isMaintenance = parseBool(rawMaint);
        const rawSiteName = data.site_name || data['general.site_name'] || settings.value.site_name;
        const rawCurrency = data.currency || data['general.currency'] || settings.value.currency;
        const logoLight = cleanString(data.logo_light || data['general.logo_light'] || data.site_logo || data['general.site_logo']);
        const logoDark = cleanString(data.logo_dark || data['general.logo_dark']);
        const favicon = cleanString(data.favicon || data['general.favicon'] || data.site_favicon || data['general.site_favicon']);
        
        const phone = cleanString(data.phone || data['social.contact_info.phone'] || data.contact_info_phone || data['social.contact_info']?.phone);
        const email = cleanString(data.email || data['social.contact_info.email'] || data.contact_info_email || data['social.contact_info']?.email);
        const address = cleanString(data.address || data['social.contact_info.address'] || data['social.contact_info']?.address);
        const hours = cleanString(data.support_hours || data['social.contact_info.support_hours'] || data['social.contact_info']?.support_hours);

        const fb = cleanString(data.facebook || data['social.links.facebook'] || data['social.links']?.facebook);
        const insta = cleanString(data.instagram || data['social.links.instagram'] || data['social.links']?.instagram);
        const tw = cleanString(data.twitter || data['social.links.twitter'] || data['social.links']?.twitter);
        const yt = cleanString(data.youtube || data['social.links.youtube'] || data['social.links']?.youtube);
        const wa = cleanString(data.whatsapp_number || data['social.whatsapp_number']);

        const guarantee = cleanString(data.guarantee_text || data['general.guarantee_text']);

        settings.value = {
          ...settings.value,
          ...data,
          site_name: cleanString(rawSiteName) || 'LaraShop',
          logo_light: logoLight || null,
          logo_dark: logoDark || null,
          favicon: favicon || null,
          site_logo: logoLight || null,
          site_favicon: favicon || null,
          phone: phone || settings.value.phone,
          contact_info_phone: phone || settings.value.phone,
          email: email || settings.value.email,
          contact_info_email: email || settings.value.email,
          address: address || settings.value.address,
          support_hours: hours || settings.value.support_hours,
          facebook: fb || settings.value.facebook,
          instagram: insta || settings.value.instagram,
          twitter: tw || settings.value.twitter,
          youtube: yt || settings.value.youtube,
          whatsapp_number: wa || settings.value.whatsapp_number,
          guarantee_text: guarantee || settings.value.guarantee_text,
          currency: cleanString(rawCurrency) || 'BDT',
          maintenance_mode: isMaintenance,
          announcement_bar_enabled: data.announcement_bar_enabled !== false && data.announcement_bar_enabled !== '0' && data.announcement_bar_enabled !== 0,
          announcement_bar_text: cleanString(data.announcement_bar_text || data['general.announcement_bar_text'] || settings.value.announcement_bar_text),
        };
      }
    } catch (e) {
      console.warn('Fallback to default public settings');
    } finally {
      isLoading.value = false;
    }
  }

  return {
    settings,
    isLoading,
    fetchSettings,
  };
});
