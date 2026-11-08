# -*- coding: utf-8 -*-
"""Ukrainian and Russian catalogues from the .pot.

    python tools/i18n.py   (after: wp i18n make-pot . languages/<domain>.pot --exclude=wporg,tools)

The source strings are English. Ukrainian keeps the translations already in the
uk .po (the free plugin on wordpress.org has been translated since 1.1.1) and adds
the new ones from T below; Russian comes from T entirely.
"""
import io
import os
import sys

import polib

HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.dirname(HERE)
DOMAIN = 'ukrposhta-shipping-for-woocommerce'
LANG = os.path.join(ROOT, 'languages')

PLURAL = {
    'uk': 'nplurals=3; plural=(n%10==1 && n%100!=11) ? 0 : ((n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20)) ? 1 : 2);',
    'ru_RU': 'nplurals=3; plural=(n%10==1 && n%100!=11) ? 0 : ((n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20)) ? 1 : 2);',
}

# msgid -> (uk, ru). Plural entries: msgid -> ((uk0, uk1, uk2), (ru0, ru1, ru2)).
T = {
    # --- plugin header / free part
    'Ukrposhta Shipping for WooCommerce': ('Ukrposhta Shipping for WooCommerce', 'Ukrposhta Shipping for WooCommerce'),
    'https://catcode.com.ua/modules/ukrposhta-shipping-for-woocommerce/': ('https://catcode.com.ua/modules/ukrposhta-shipping-for-woocommerce/', 'https://catcode.com.ua/modules/ukrposhta-shipping-for-woocommerce/'),
    'Ukrposhta delivery for WooCommerce: the customer picks region, city and post office at checkout from the official Address Classifier, and the delivery price is quoted live. Pro: shipments with cash on delivery, labels, form 103a and delivery statuses.': (
        'Доставка Укрпоштою для WooCommerce: покупець обирає область, місто і відділення в чекауті з офіційного Адресного класифікатора, вартість доставки рахується наживо. Pro: відправлення з післяплатою, ярлики, форма 103а і статуси доставки.',
        'Доставка Укрпочтой для WooCommerce: покупатель выбирает область, город и отделение в чекауте из официального Адресного классификатора, стоимость доставки считается вживую. Pro: отправления с наложенным платежом, ярлыки, форма 103а и статусы доставки.'),
    'CatCode': ('CatCode', 'CatCode'),
    'https://catcode.com.ua': ('https://catcode.com.ua', 'https://catcode.com.ua'),
    'Ukrposhta': ('Укрпошта', 'Укрпочта'),
    'Ukrposhta delivery: the customer picks a post office at checkout (region -> city -> office) and the tariff is quoted live.': (None, 'Доставка Укрпочтой: выбор отделения в чекауте (область → город → отделение) и расчёт тарифа вживую.'),
    'Enable': (None, 'Включить'),
    'Show Ukrposhta at checkout': (None, 'Показывать «Укрпочта» в чекауте'),
    'Method title': (None, 'Название метода'),
    'Ukrposhta - delivery to a post office': (None, 'Укрпочта — доставка в отделение'),
    'Test environment': (None, 'Тестовая среда'),
    'Sandbox (dev.ukrposhta.ua)': (None, 'Sandbox (dev.ukrposhta.ua)'),
    'Bearer eCom': (None, 'Bearer eCom'),
    'Issued by Ukrposhta once your eCom contract is signed. Needed for both the address classifier and the tariff. Stored encrypted. Leave empty to keep the current key.': (None, 'Выдаётся Укрпочтой после подписания договора eCom. Нужен для классификатора адресов и тарифа. Хранится зашифрованным. Оставьте пустым, чтобы не менять.'),
    'Sender post index': (None, 'Индекс отправителя'),
    'Post index the parcels are sent from. Required for the tariff.': (None, 'Почтовый индекс, откуда отправляются посылки. Нужен для тарифа.'),
    'Service type': (None, 'Тип услуги'),
    'Ukrposhta Standard': (None, 'Укрпочта Стандарт'),
    'Ukrposhta Express': (None, 'Укрпочта Экспресс'),
    'Fallback rate, UAH': (None, 'Тариф по умолчанию, грн'),
    'Used when the API is unreachable or no recipient index has been chosen yet.': (None, 'Используется, когда API недоступен или индекс получателя ещё не выбран.'),
    'Free shipping from, UAH': (None, 'Бесплатно от суммы, грн'),
    '0 disables it.': (None, '0 — выключено.'),
    'Declared value': (None, 'Объявленная ценность'),
    'Send the order total as the declared value': (None, 'Передавать сумму заказа как объявленную ценность'),
    'Ukrposhta charges a percentage for this: the quote gets higher, but the parcel is insured.': (None, 'Укрпочта берёт за это процент: тариф будет выше, зато посылка застрахована.'),
    'Cash-on-delivery payment methods': (None, 'Способы оплаты = наложенный платёж'),
    'Comma-separated payment method IDs. For these the Ukrposhta cash-on-delivery commission is added to the tariff. The standard WooCommerce method is <code>cod</code>.': (None, 'ID платёжных методов через запятую. Для них в тариф добавляется комиссия Укрпочты за наложенный платёж. Стандартный метод WooCommerce — <code>cod</code>.'),
    'Widget accent colour': (None, 'Акцентный цвет виджета'),
    'Please choose a region, a city and an Ukrposhta post office.': (None, 'Выберите область, город и отделение Укрпочты.'),
    'Ukrposhta post office:': (None, 'Отделение Укрпочты:'),
    'Ukrposhta was not chosen for this order.': (None, 'Для этого заказа Укрпочту не выбирали.'),
    'City:': (None, 'Город:'),
    'Post office:': (None, 'Отделение:'),
    'Post index:': (None, 'Индекс:'),
    'Ukrposhta Shipping for WooCommerce requires an active WooCommerce installation.': (None, 'Для работы плагина нужен активный WooCommerce.'),
    'Ukrposhta delivery': (None, 'Доставка Укрпочтой'),
    'Region': (None, 'Область'),
    'Choose a region…': (None, 'Выберите область…'),
    'City': (None, 'Город'),
    'Choose a region first': (None, 'Сначала выберите область'),
    'Start typing the name…': (None, 'Начните вводить название…'),
    'Post office': (None, 'Отделение'),
    'Choose a city first': (None, 'Сначала выберите город'),
    'Pick a post office or an index…': (None, 'Выберите отделение или индекс…'),
    'Searching…': (None, 'Поиск…'),
    'Loading…': (None, 'Загрузка…'),
    'Loading regions…': (None, 'Загрузка областей…'),
    'Regions are unavailable. Check the connection to Ukrposhta.': (None, 'Области недоступны. Проверьте подключение к Укрпочте.'),
    'Nothing found': (None, 'Ничего не найдено'),
    'No post offices here': (None, 'Отделений нет'),
    'The Pro version of Ukrposhta Shipping is paused: the free copy "CatCode Shipping with Ukrposhta for WooCommerce" from WordPress.org is active and uses the same classes. Deactivate it and Pro takes over from the next page load. The settings stay. Deactivate the free copy, do not delete it: deleting it removes the shared settings and post office table.': (None, 'Pro-версия Укрпочты на паузе: активна бесплатная копия «CatCode Shipping with Ukrposhta for WooCommerce» с WordPress.org с теми же классами. Деактивируйте её — со следующей загрузки заработает Pro. Настройки сохранятся. Не удаляйте бесплатную копию, только деактивируйте: её удаление стирает общие настройки и таблицу отделений.'),
    'International shipments': ('Міжнародні відправлення', 'Международные отправления'),
    'Quote deliveries outside Ukraine': ('Рахувати доставку за межі України', 'Считать доставку за пределы Украины'),
    'The rate is keyed by destination country and weight, no post office is picked. Add this method to a zone that contains the countries you ship to.': ('Тариф рахується за країною і вагою, відділення не обирається. Додайте цей метод у зону з країнами, куди відправляєте.', 'Тариф считается по стране и весу, отделение не выбирается. Добавьте этот метод в зону со странами, куда отправляете.'),
    'Transport type': ('Тип транспорту', 'Тип транспорта'),
    'Air': ('Авіа', 'Авиа'),
    'Ground': ('Наземний', 'Наземный'),
    'Package type': ('Тип відправлення', 'Тип отправления'),
    'Not every type is available for every country: an unavailable combination is reported by Ukrposhta and the method is then not offered.': ('Не кожен тип доступний для кожної країни: про недоступне поєднання повідомляє Укрпошта, і тоді метод не показується.', 'Не каждый тип доступен для каждой страны: о недоступном сочетании сообщает Укрпочта, и тогда метод не показывается.'),
    'Content category': ('Категорія вмісту', 'Категория содержимого'),
    'Tariff currency': ('Валюта тарифу', 'Валюта тарифа'),
    'Some destinations (the US among them) are only quoted in USD.': ('Для деяких країн (зокрема США) тариф дається лише в USD.', 'Для некоторых стран (в том числе США) тариф даётся только в USD.'),
    'International fallback rate, UAH': ('Запасний міжнародний тариф, грн', 'Запасной международный тариф, грн'),
    'Leave empty so a failed quote hides the method instead of inventing a price. The reason is written to WooCommerce - Status - Logs.': ('Залиште порожнім: тоді невдалий розрахунок ховає метод, а не вигадує ціну. Причина пишеться в WooCommerce → Статус → Журнали.', 'Оставьте пустым: тогда неудачный расчёт скрывает метод, а не выдумывает цену. Причина пишется в WooCommerce → Статус → Журналы.'),
    'international': ('за кордон', 'за границу'),

    # --- Pro settings
    'Pro: shipments, labels, tracking': ('Pro: відправлення, ярлики, трекінг', 'Pro: отправления, ярлыки, трекинг'),
    'Shipment creation, cash on delivery, labels, the form 103a registry and delivery statuses open with a Pro licence or the 7-day trial — see the licence box at the top of this page. Everything above keeps working without it.': (
        'Створення відправлень, післяплата, ярлики, реєстр форми 103а і статуси доставки відкриваються з Pro-ліцензією або 7-денним пробним періодом — блок ліцензії вгорі сторінки. Усе, що вище, працює і без неї.',
        'Создание отправлений, наложенный платёж, ярлыки, реестр формы 103а и статусы доставки открываются с Pro-лицензией или 7-дневным пробным периодом — блок лицензии вверху страницы. Всё, что выше, работает и без неё.'),
    'Do not change': ('Не змінювати', 'Не менять'),
    'Pro: shipments': ('Pro: відправлення', 'Pro: отправления'),
    'For shipments Ukrposhta issues a counterparty token together with the Bearer key once the contract is signed.': ('Для відправлень Укрпошта разом із Bearer-ключем видає токен контрагента після підписання договору.', 'Для отправлений Укрпочта вместе с Bearer-ключом выдаёт токен контрагента после подписания договора.'),
    'Counterparty token (user token)': ('Токен контрагента (user token)', 'Токен контрагента (user token)'),
    'PROD_COUNTERPARTY TOKEN from the access table of the contract (SAND_COUNTERPARTY TOKEN for the sandbox). Stored encrypted. Leave empty to keep the current one.': ('PROD_COUNTERPARTY TOKEN з таблиці доступів до договору (SAND_COUNTERPARTY TOKEN для тестового середовища). Зберігається зашифровано. Залиште порожнім, щоб не змінювати.', 'PROD_COUNTERPARTY TOKEN из таблицы доступов к договору (SAND_COUNTERPARTY TOKEN для тестовой среды). Хранится зашифрованным. Оставьте пустым, чтобы не менять.'),
    'Bearer StatusTracking': ('Bearer StatusTracking', 'Bearer StatusTracking'),
    'For delivery statuses. Empty — the Bearer eCom key is used. Stored encrypted.': ('Для статусів доставки. Порожньо — береться Bearer eCom. Зберігається зашифровано.', 'Для статусов доставки. Пусто — используется Bearer eCom. Хранится зашифрованным.'),
    'Sender type': ('Тип відправника', 'Тип отправителя'),
    'Individual': ('Фізична особа', 'Физическое лицо'),
    'Private entrepreneur (FOP)': ('ФОП', 'ФЛП'),
    'Company': ('Юридична особа', 'Юридическое лицо'),
    'Sender last name': ('Прізвище відправника', 'Фамилия отправителя'),
    'Sender first name': ("Ім'я відправника", 'Имя отправителя'),
    'Sender middle name': ('По батькові відправника', 'Отчество отправителя'),
    'Required by Ukrposhta for cash on delivery to an individual sender.': ('Укрпошта вимагає його для післяплати, коли відправник — фізична особа.', 'Укрпочта требует его для наложенного платежа, когда отправитель — физлицо.'),
    'Company or FOP name': ('Назва компанії або ФОП', 'Название компании или ФЛП'),
    'For a company sender.': ('Для відправника-компанії.', 'Для отправителя-компании.'),
    'EDRPOU / tax number': ('ЄДРПОУ / ІПН', 'ЕГРПОУ / ИНН'),
    'EDRPOU for a company, the 10-digit tax number for a FOP.': ('ЄДРПОУ для компанії, 10-значний ІПН для ФОП.', 'ЕГРПОУ для компании, 10-значный ИНН для ФЛП.'),
    'Sender phone': ('Телефон відправника', 'Телефон отправителя'),
    'IBAN for cash on delivery': ('IBAN для післяплати', 'IBAN для наложенного платежа'),
    'For a FOP or a company: the cash on delivery is transferred to this account. An individual receives it at the post office.': ('Для ФОП або компанії: післяплата переказується на цей рахунок. Фізична особа отримує її у відділенні.', 'Для ФЛП или компании: наложенный платёж переводится на этот счёт. Физлицо получает его в отделении.'),
    'Sender ID at Ukrposhta': ('ID відправника в Укрпошті', 'ID отправителя в Укрпочте'),
    'Filled in by the "Create sender" button above. If you already have a sender uuid from Ukrposhta, paste it here.': ('Заповнюється кнопкою «Створити відправника» вгорі. Якщо uuid відправника від Укрпошти у вас уже є, вставте його сюди.', 'Заполняется кнопкой «Создать отправителя» вверху. Если uuid отправителя от Укрпочты у вас уже есть, вставьте его сюда.'),
    'Who pays for delivery': ('Хто платить за доставку', 'Кто платит за доставку'),
    'The recipient, at the post office': ('Отримувач, у відділенні', 'Получатель, в отделении'),
    'The shop (sender)': ('Магазин (відправник)', 'Магазин (отправитель)'),
    'Cash on delivery': ('Післяплата', 'Наложенный платёж'),
    'Add cash on delivery to the shipment for orders paid on collection': ('Додавати післяплату у відправлення для замовлень з оплатою при отриманні', 'Добавлять наложенный платёж в отправление для заказов с оплатой при получении'),
    'The payment methods are the ones in "Cash-on-delivery payment methods" above.': ('Способи оплати — ті, що в полі «Способи оплати = післяплата» вище.', 'Способы оплаты — те, что в поле «Способы оплаты = наложенный платёж» выше.'),
    'Cash on delivery amount': ('Сума післяплати', 'Сумма наложенного платежа'),
    'Order total': ('Сума замовлення', 'Сумма заказа'),
    'Goods only, without shipping': ('Лише товари, без доставки', 'Только товары, без доставки'),
    'Cash on delivery fee': ('Комісія за післяплату', 'Комиссия за наложенный платёж'),
    'Paid by the recipient': ('Платить отримувач', 'Платит получатель'),
    'Paid by the shop': ('Платить магазин', 'Платит магазин'),
    'Weight when products have none, kg': ('Вага, якщо в товарів її немає, кг', 'Вес, если у товаров его нет, кг'),
    'Parcel length, cm': ('Довжина посилки, см', 'Длина посылки, см'),
    'Parcel width, cm': ('Ширина посилки, см', 'Ширина посылки, см'),
    'Parcel height, cm': ('Висота посилки, см', 'Высота посылки, см'),
    'If the recipient does not collect it': ('Якщо отримувач не забрав', 'Если получатель не забрал'),
    'Return to the sender': ('Повернути відправнику', 'Вернуть отправителю'),
    'Return after 7 days of storage': ('Повернути після 7 днів зберігання', 'Вернуть после 7 дней хранения'),
    'Treat as a refusal': ('Вважати відмовою', 'Считать отказом'),
    'SMS to the recipient': ('SMS отримувачу', 'SMS получателю'),
    'Ukrposhta sends an SMS when the parcel arrives': ('Укрпошта надсилає SMS, коли посилка прибуде', 'Укрпочта отправляет SMS, когда посылка прибудет'),
    'Label format': ('Формат ярлика', 'Формат ярлыка'),
    '100x100 mm (label printer)': ('100×100 мм (термопринтер)', '100×100 мм (термопринтер)'),
    'A4 sheet': ('Аркуш A4', 'Лист A4'),
    'A5 sheet': ('Аркуш A5', 'Лист A5'),
    'Delivery statuses': ('Статуси доставки', 'Статусы доставки'),
    'Check the statuses of open shipments every hour': ('Перевіряти статуси відкритих відправлень щогодини', 'Проверять статусы открытых отправлений каждый час'),
    'Order status when delivered': ('Статус замовлення після вручення', 'Статус заказа после вручения'),
    'Order status when returned': ('Статус замовлення після повернення', 'Статус заказа после возврата'),
    'Tracking for the customer': ('Трекінг для покупця', 'Трекинг для покупателя'),
    'Show the tracking number and link in the order e-mails and the customer account': ('Показувати номер і посилання для відстеження в листах і в кабінеті покупця', 'Показывать номер и ссылку для отслеживания в письмах и в кабинете покупателя'),

    # --- Pro admin UI
    'Working…': ('Виконується…', 'Выполняется…'),
    'No answer from the server. Check the connection and try again.': ('Сервер не відповів. Перевірте з’єднання і спробуйте ще раз.', 'Сервер не ответил. Проверьте соединение и попробуйте ещё раз.'),
    'Delete this shipment at Ukrposhta?': ('Видалити це відправлення в Укрпошті?', 'Удалить это отправление в Укрпочте?'),
    'Ukrposhta Pro': ('Укрпошта Pro', 'Укрпочта Pro'),
    'Pro is on': ('Pro увімкнено', 'Pro включён'),
    'Free': ('Free', 'Free'),
    'Licence:': ('Ліцензія:', 'Лицензия:'),
    'until %s': ('до %s', 'до %s'),
    'The trial has ended. Buy a licence to turn the Pro features back on — the settings are kept.': ('Пробний період завершився. Купіть ліцензію, щоб знову увімкнути Pro — налаштування збережено.', 'Пробный период закончился. Купите лицензию, чтобы снова включить Pro — настройки сохранены.'),
    'Buy Pro': ('Купити Pro', 'Купить Pro'),
    'Pro adds to the free picker and tariff:': ('Pro додає до безкоштовного вибору відділення і тарифу:', 'Pro добавляет к бесплатному выбору отделения и тарифу:'),
    'Activate key': ('Активувати ключ', 'Активировать ключ'),
    'Check again': ('Перевірити ще раз', 'Проверить ещё раз'),
    'Release the key on this site': ('Звільнити ключ на цьому сайті', 'Освободить ключ на этом сайте'),
    'E-mail for the trial key': ('E-mail для пробного ключа', 'E-mail для пробного ключа'),
    'Try Pro for 7 days': ('Спробувати Pro 7 днів', 'Попробовать Pro 7 дней'),
    'Buy a licence': ('Купити ліцензію', 'Купить лицензию'),
    'Before the first shipment:': ('Перед першим відправленням:', 'Перед первым отправлением:'),
    'Create sender': ('Створити відправника', 'Создать отправителя'),
    'Uses the saved sender details below; save the settings first.': ('Бере збережені дані відправника нижче — спершу збережіть налаштування.', 'Берёт сохранённые данные отправителя ниже — сначала сохраните настройки.'),
    'You are not allowed to do this.': ('У вас немає прав на цю дію.', 'У вас нет прав на это действие.'),
    'The licence is active — the Pro features are on.': ('Ліцензія активна — Pro-функції увімкнено.', 'Лицензия активна — Pro-функции включены.'),
    'The trial has started — Pro works until %s. The key has also been e-mailed to you.': ('Пробний період почався — Pro працює до %s. Ключ також надіслано вам на пошту.', 'Пробный период начался — Pro работает до %s. Ключ также отправлен вам на почту.'),
    'The trial has started. The key has also been e-mailed to you.': ('Пробний період почався. Ключ також надіслано вам на пошту.', 'Пробный период начался. Ключ также отправлен вам на почту.'),
    'The key is released on this site. The free features keep working.': ('Ключ звільнено на цьому сайті. Безкоштовні функції працюють далі.', 'Ключ освобождён на этом сайте. Бесплатные функции работают дальше.'),
    'Creating shipments is a Pro feature.': ('Створення відправлень — функція Pro.', 'Создание отправлений — функция Pro.'),
    'Order not found.': ('Замовлення не знайдено.', 'Заказ не найден.'),
    'Status updated: %s': ('Статус оновлено: %s', 'Статус обновлён: %s'),
    'No new status yet. Ukrposhta shows a shipment after it is accepted at the post office.': ('Нового статусу поки немає. Укрпошта показує відправлення після приймання у відділенні.', 'Нового статуса пока нет. Укрпочта показывает отправление после приёма в отделении.'),
    'Ukrposhta shipment': ('Відправлення Укрпошти', 'Отправление Укрпочты'),
    'No Ukrposhta post office was picked for this order.': ('Для цього замовлення не обрано відділення Укрпошти.', 'Для этого заказа не выбрано отделение Укрпочты.'),
    'Shipment creation, labels and delivery statuses are part of Pro. Start the 7-day trial or enter a key in the Ukrposhta settings.': ('Створення відправлень, ярлики і статуси доставки — частина Pro. Запустіть 7-денний пробний період або введіть ключ у налаштуваннях Укрпошти.', 'Создание отправлений, ярлыки и статусы доставки — часть Pro. Запустите 7-дневный пробный период или введите ключ в настройках Укрпочты.'),
    'Open the settings': ('Відкрити налаштування', 'Открыть настройки'),
    'Weight': ('Вага', 'Вес'),
    'kg': ('кг', 'кг'),
    'no': ('ні', 'нет'),
    'Create shipment': ('Створити відправлення', 'Создать отправление'),
    'Barcode': ('ШКІ (номер)', 'ШКИ (номер)'),
    'Status': ('Статус', 'Статус'),
    'Delivery by Ukrposhta': ('Доставка за тарифом Укрпошти', 'Доставка по тарифу Укрпочты'),
    'Label (PDF)': ('Ярлик (PDF)', 'Ярлык (PDF)'),
    'Update status': ('Оновити статус', 'Обновить статус'),
    'Delete shipment': ('Видалити відправлення', 'Удалить отправление'),
    'Back': ('Назад', 'Назад'),
    'Ukrposhta: create shipments': ('Укрпошта: створити відправлення', 'Укрпочта: создать отправления'),
    'Ukrposhta: print labels': ('Укрпошта: надрукувати ярлики', 'Укрпочта: напечатать ярлыки'),
    'Ukrposhta: form 103a registry': ('Укрпошта: реєстр ф. 103а', 'Укрпочта: реестр ф. 103а'),
    'Ukrposhta: update statuses': ('Укрпошта: оновити статуси', 'Укрпочта: обновить статусы'),
    '#%s: not a Ukrposhta order': ('#%s: не замовлення Укрпошти', '#%s: не заказ Укрпочты'),
    'Ukrposhta: status changed in %d order.': (
        ('Укрпошта: статус змінився в %d замовленні.', 'Укрпошта: статус змінився в %d замовленнях.', 'Укрпошта: статус змінився в %d замовленнях.'),
        ('Укрпочта: статус изменился в %d заказе.', 'Укрпочта: статус изменился в %d заказах.', 'Укрпочта: статус изменился в %d заказах.')),
    'Ukrposhta: %d shipment created.': (
        ('Укрпошта: створено %d відправлення.', 'Укрпошта: створено %d відправлення.', 'Укрпошта: створено %d відправлень.'),
        ('Укрпочта: создано %d отправление.', 'Укрпочта: создано %d отправления.', 'Укрпочта: создано %d отправлений.')),
    'no shipment yet': ('відправлення ще немає', 'отправления ещё нет'),
    'Ukrposhta tracking number: %1$s — %2$s': ('Номер відстеження Укрпошти: %1$s — %2$s', 'Номер отслеживания Укрпочты: %1$s — %2$s'),
    'Tracking number: %s': ('Номер для відстеження: %s', 'Номер для отслеживания: %s'),
    'Status:': ('Статус:', 'Статус:'),

    # --- Pro features list
    'Shipment (barcode) from the order in one click: recipient, post office, weight, declared value': ('Відправлення (ШКІ) із замовлення в один клік: отримувач, відділення, вага, оголошена цінність', 'Отправление (ШКИ) из заказа в один клик: получатель, отделение, вес, объявленная ценность'),
    'Cash on delivery in the shipment for orders paid on collection': ('Післяплата у відправленні для замовлень з оплатою при отриманні', 'Наложенный платёж в отправлении для заказов с оплатой при получении'),
    'Address labels 100x100, A4 or A5 as PDF, one by one or for many orders at once': ('Адресні ярлики 100×100, A4 або A5 у PDF — по одному або для багатьох замовлень разом', 'Адресные ярлыки 100×100, A4 или A5 в PDF — по одному или для многих заказов сразу'),
    'Bulk actions in the order list: create shipments and print labels': ('Масові дії у списку замовлень: створити відправлення і надрукувати ярлики', 'Массовые действия в списке заказов: создать отправления и напечатать ярлыки'),
    'Form 103a registry for handing a batch of parcels over at the post office': ('Реєстр форми 103а, щоб здати партію посилок у відділенні', 'Реестр формы 103а, чтобы сдать партию посылок в отделении'),
    'Delivery statuses in the order and the order completed on delivery': ('Статуси доставки в замовленні, після вручення замовлення стає виконаним', 'Статусы доставки в заказе, после вручения заказ становится выполненным'),
    'Tracking number and link in the customer e-mails and the customer account': ('Номер і посилання для відстеження в листах і в кабінеті покупця', 'Номер и ссылка для отслеживания в письмах и в кабинете покупателя'),
    'Delete a shipment before it is handed over at the post office': ('Видалення відправлення, доки його не здали у відділенні', 'Удаление отправления, пока его не сдали в отделении'),

    # --- licence states and errors
    'not checked yet': ('ще не перевірено', 'ещё не проверено'),
    'purchased — the Pro features are yours for good': ('куплено — Pro-функції ваші назавжди', 'куплено — Pro-функции ваши навсегда'),
    'purchased — the Pro features are yours for good; the updates and support term has ended': ('куплено — Pro-функції ваші назавжди; строк оновлень і підтримки завершився', 'куплено — Pro-функции ваши навсегда; срок обновлений и поддержки закончился'),
    'trial — %d day left': (
        ('пробний період — лишився %d день', 'пробний період — лишилось %d дні', 'пробний період — лишилось %d днів'),
        ('пробный период — остался %d день', 'пробный период — осталось %d дня', 'пробный период — осталось %d дней')),
    'trial active': ('пробний період активний', 'пробный период активен'),
    'active — the Pro features are on': ('активна — Pro-функції увімкнено', 'активна — Pro-функции включены'),
    'not valid (last check %s)': ('недійсна (остання перевірка %s)', 'недействительна (последняя проверка %s)'),
    'Key not found. Check that it was copied in full, without spaces.': ('Ключ не знайдено. Перевірте, що його скопійовано повністю, без пробілів.', 'Ключ не найден. Проверьте, что он скопирован полностью, без пробелов.'),
    'This key no longer works. If this is a mistake, write to support.': ('Цей ключ більше не діє. Якщо це помилка, напишіть у підтримку.', 'Этот ключ больше не действует. Если это ошибка, напишите в поддержку.'),
    'The 7-day trial has ended. Buy a licence on catcode.com.ua to turn the Pro features back on.': ('7-денний пробний період завершився. Купіть ліцензію на catcode.com.ua, щоб знову увімкнути Pro.', '7-дневный пробный период закончился. Купите лицензию на catcode.com.ua, чтобы снова включить Pro.'),
    'This key is already used on another shop. Release it there first.': ('Цей ключ уже використовується на іншому магазині. Спершу звільніть його там.', 'Этот ключ уже используется на другом магазине. Сначала освободите его там.'),
    'This key belongs to another CatCode product, not to Ukrposhta Shipping.': ('Цей ключ від іншого продукту CatCode, не від Укрпошти.', 'Этот ключ от другого продукта CatCode, не от Укрпочты.'),
    'Could not reach our licence server. We will retry automatically in a few hours.': ('Не вдалося зв’язатися з нашим сервером ліцензій. Спробуємо ще раз автоматично за кілька годин.', 'Не удалось связаться с нашим сервером лицензий. Попробуем ещё раз автоматически через несколько часов.'),
    'Enter a licence key.': ('Введіть ключ ліцензії.', 'Введите ключ лицензии.'),
    'No key saved yet.': ('Ключ ще не збережено.', 'Ключ ещё не сохранён.'),
    'Enter a valid e-mail address — the key will be sent there.': ('Введіть правильну адресу e-mail — ключ прийде туди.', 'Введите правильный адрес e-mail — ключ придёт туда.'),
    'A trial has already been issued for this e-mail address.': ('Для цієї адреси пробний період уже видавався.', 'Для этого адреса пробный период уже выдавался.'),
    'The trial has already been used on this site — one per install.': ('На цьому сайті пробний період уже використано — один на встановлення.', 'На этом сайте пробный период уже использован — один на установку.'),
    'Too many attempts. Try again in an hour.': ('Забагато спроб. Спробуйте за годину.', 'Слишком много попыток. Попробуйте через час.'),
    'The server answered with something we could not read. Try again in a few minutes.': ('Сервер відповів незрозуміло. Спробуйте за кілька хвилин.', 'Сервер ответил непонятно. Попробуйте через несколько минут.'),
    'This key does not fit.': ('Цей ключ не підходить.', 'Этот ключ не подходит.'),

    # --- shipment statuses
    'Created, waiting to be handed over': ('Створено, чекає на здачу у відділенні', 'Создано, ждёт сдачи в отделении'),
    'Accepted at the post office': ('Прийнято у відділенні', 'Принято в отделении'),
    'On the way': ('У дорозі', 'В пути'),
    'Arrived at the post office': ('Прибуло у відділення', 'Прибыло в отделение'),
    'In storage': ('На зберіганні', 'На хранении'),
    'Not handed over, being forwarded': ('Не вручено, переадресація', 'Не вручено, переадресация'),
    'Delivered': ('Вручено', 'Вручено'),
    'Returned to the sender': ('Повернуто відправнику', 'Возвращено отправителю'),
    'Acceptance cancelled': ('Приймання скасовано', 'Приём отменён'),
    'Deleted': ('Видалено', 'Удалено'),

    # --- shipment messages
    'Enter the Bearer eCom key.': ('Введіть ключ Bearer eCom.', 'Введите ключ Bearer eCom.'),
    'Enter the counterparty token (user token) from the Ukrposhta contract.': ('Введіть токен контрагента (user token) з договору з Укрпоштою.', 'Введите токен контрагента (user token) из договора с Укрпочтой.'),
    'Enter the sender post index.': ('Введіть індекс відправника.', 'Введите индекс отправителя.'),
    'Create the sender: fill in the sender details and press "Create sender".': ('Створіть відправника: заповніть його дані й натисніть «Створити відправника».', 'Создайте отправителя: заполните его данные и нажмите «Создать отправителя».'),
    'Save the Bearer eCom key and the counterparty token first.': ('Спершу збережіть ключ Bearer eCom і токен контрагента.', 'Сначала сохраните ключ Bearer eCom и токен контрагента.'),
    'Fill in the sender post index and phone and save the settings.': ('Заповніть індекс і телефон відправника та збережіть налаштування.', 'Заполните индекс и телефон отправителя и сохраните настройки.'),
    'Ukrposhta did not accept the sender address': ('Укрпошта не прийняла адресу відправника', 'Укрпочта не приняла адрес отправителя'),
    'Ukrposhta did not create the sender': ('Укрпошта не створила відправника', 'Укрпочта не создала отправителя'),
    'Sender created: %s': ('Відправника створено: %s', 'Отправитель создан: %s'),
    'Ukrposhta did not answer, try again in a minute.': ('Укрпошта не відповіла, спробуйте за хвилину.', 'Укрпочта не ответила, попробуйте через минуту.'),
    'The shipment already exists: %s': ('Відправлення вже є: %s', 'Отправление уже есть: %s'),
    'The shipment for this order is being created right now. Reload the page in a few seconds.': ('Відправлення для цього замовлення саме створюється. Оновіть сторінку за кілька секунд.', 'Отправление для этого заказа как раз создаётся. Обновите страницу через несколько секунд.'),
    'The customer phone is missing or too short — Ukrposhta needs a mobile number for delivery to a post office.': ('Телефону покупця немає або він закороткий — для доставки у відділення Укрпошті потрібен мобільний номер.', 'Телефона покупателя нет или он слишком короткий — для доставки в отделение Укрпочте нужен мобильный номер.'),
    'Ukrposhta needs the first and last name of the recipient, at least 2 letters each. Edit the shipping name of the order.': ("Укрпошті потрібні ім'я і прізвище отримувача, щонайменше по 2 літери. Виправте ім'я в доставці замовлення.", 'Укрпочте нужны имя и фамилия получателя, минимум по 2 буквы. Исправьте имя в доставке заказа.'),
    'Ukrposhta did not accept the recipient post office': ('Укрпошта не прийняла відділення отримувача', 'Укрпочта не приняла отделение получателя'),
    'Ukrposhta did not create the recipient': ('Укрпошта не створила отримувача', 'Укрпочта не создала получателя'),
    'Order %s': ('Замовлення %s', 'Заказ %s'),
    'Ukrposhta did not create the shipment': ('Укрпошта не створила відправлення', 'Укрпочта не создала отправление'),
    'Ukrposhta: shipment %1$s created, cash on delivery %2$s.': ('Укрпошта: створено відправлення %1$s, післяплата %2$s.', 'Укрпочта: создано отправление %1$s, наложенный платёж %2$s.'),
    'Ukrposhta: shipment %s created.': ('Укрпошта: створено відправлення %s.', 'Укрпочта: создано отправление %s.'),
    'Shipment created: %s': ('Відправлення створено: %s', 'Отправление создано: %s'),
    'Deleting shipments is a Pro feature.': ('Видалення відправлень — функція Pro.', 'Удаление отправлений — функция Pro.'),
    'This order has no shipment.': ('У цього замовлення немає відправлення.', 'У этого заказа нет отправления.'),
    'The shipment is already accepted at the post office and cannot be deleted.': ('Відправлення вже прийняте у відділенні, видалити його не можна.', 'Отправление уже принято в отделении, удалить его нельзя.'),
    'Ukrposhta did not delete the shipment': ('Укрпошта не видалила відправлення', 'Укрпочта не удалила отправление'),
    'Ukrposhta: shipment %s deleted.': ('Укрпошта: відправлення %s видалено.', 'Укрпочта: отправление %s удалено.'),
    'Shipment %s deleted.': ('Відправлення %s видалено.', 'Отправление %s удалено.'),
    'Printing labels is a Pro feature.': ('Друк ярликів — функція Pro.', 'Печать ярлыков — функция Pro.'),
    'None of the selected orders has a shipment yet. Create the shipments first.': ('У жодного з обраних замовлень ще немає відправлення. Спершу створіть відправлення.', 'Ни у одного из выбранных заказов ещё нет отправления. Сначала создайте отправления.'),
    'Ukrposhta did not return the labels': ('Укрпошта не повернула ярлики', 'Укрпочта не вернула ярлыки'),
    'The form 103a registry is a Pro feature.': ('Реєстр форми 103а — функція Pro.', 'Реестр формы 103а — функция Pro.'),
    'Shop %s': ('Магазин %s', 'Магазин %s'),
    'Ukrposhta did not create the shipment group': ('Укрпошта не створила групу відправлень', 'Укрпочта не создала группу отправлений'),
    'Ukrposhta did not add the shipments to the group: %s': ('Укрпошта не додала відправлення до групи: %s', 'Укрпочта не добавила отправления в группу: %s'),
    'Ukrposhta did not return form 103a': ('Укрпошта не повернула форму 103а', 'Укрпочта не вернула форму 103а'),
    'Ukrposhta %1$s: %2$s%3$s': ('Укрпошта %1$s: %2$s%3$s', 'Укрпочта %1$s: %2$s%3$s'),
    'Ukrposhta status:': ('Статус Укрпошти:', 'Статус Укрпочты:'),
}


def build(locale):
    pot = polib.pofile(os.path.join(LANG, DOMAIN + '.pot'), wrapwidth=0)
    old_path = os.path.join(LANG, '%s-%s.po' % (DOMAIN, locale))
    old = {}
    if locale == 'uk' and os.path.exists(old_path):
        old = {e.msgid: e for e in polib.pofile(old_path) if e.msgstr or e.msgstr_plural}
    po = polib.POFile(wrapwidth=0)
    po.metadata = {
        'Project-Id-Version': 'Ukrposhta Shipping for WooCommerce',
        'Report-Msgid-Bugs-To': 'https://catcode.com.ua/',
        'Language': locale,
        'MIME-Version': '1.0',
        'Content-Type': 'text/plain; charset=UTF-8',
        'Content-Transfer-Encoding': '8bit',
        'Plural-Forms': PLURAL[locale],
        'X-Domain': DOMAIN,
    }
    idx = 0 if locale == 'uk' else 1
    missing = []
    for e in pot:
        n = polib.POEntry(msgid=e.msgid, msgid_plural=e.msgid_plural, occurrences=e.occurrences, comment=e.comment, msgctxt=e.msgctxt)
        tr = T.get(e.msgid, (None, None))[idx]
        if tr is None and locale == 'uk' and e.msgid in old:
            o = old[e.msgid]
            if e.msgid_plural:
                n.msgstr_plural = dict(o.msgstr_plural)
            else:
                n.msgstr = o.msgstr
        elif tr is None:
            missing.append(e.msgid)
            if e.msgid_plural:
                n.msgstr_plural = {0: '', 1: '', 2: ''}
        elif e.msgid_plural:
            n.msgstr_plural = {0: tr[0], 1: tr[1], 2: tr[2]}
        else:
            n.msgstr = tr
        po.append(n)
    if missing:
        sys.exit('%s: no translation for %d strings, first: %r' % (locale, len(missing), missing[0]))
    po.save(old_path)
    po.save_as_mofile(old_path[:-3] + '.mo')
    print(locale, len(po), 'strings, 100%')


for loc in ('uk', 'ru_RU'):
    build(loc)
