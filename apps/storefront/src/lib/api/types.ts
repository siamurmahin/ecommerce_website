import type { components, paths } from "@looks-jhorna/api-types";

type Schemas = components["schemas"];
type Json<P extends keyof paths> = paths[P] extends { get: { responses: { 200: { content: { "application/json": infer T } } } } } ? T : never;

export type Category = Schemas["CategoryResource"];
export type ProductCard = Schemas["ProductCardResource"];
export type Product = Schemas["ProductResource"];
export type ProductBadge = Schemas["ProductBadge"];
export type ProductOption = Product["options"][number];
export type ProductVariant = Product["variants"][number];

export type HomeData = Json<"/v1/home">;
export type Banner = Schemas["BannerResource"];
export type HomeSection = Schemas["HomeSectionResource"];
export type Menus = Json<"/v1/menus">;
export type SiteSettings = Json<"/v1/settings">;
export type SitemapData = Json<"/v1/sitemap">;
export type TrackingConfig = Json<"/v1/tracking">;
export type TrackEventInput = Schemas["TrackEventRequest"];
export type SocialNetwork = SiteSettings["socials"][number]["network"];
export type MenuItem = Schemas["MenuItemResource"];
export type Page = Schemas["PageResource"];
export type PageList = Json<"/v1/pages">;
export type LandingPage = Schemas["LandingPageResource"];
export type LandingBlock = Schemas["LandingBlockResource"];
export type LandingList = Json<"/v1/landing-pages">;
export type ProductPage = Json<"/v1/products">;
export type ProductSort = "newest" | "price_asc" | "price_desc" | "popular";
/** The generated type widens `products` oddly; they are ProductCardResource items. */
export type SearchSuggest = Omit<Json<"/v1/search/suggest">, "products"> & { products: ProductCard[] };

export type CheckoutOptions = Json<"/v1/checkout/options">;
export type DeliveryZone = Schemas["DeliveryZoneResource"];
export type Quote = Schemas["QuoteResource"];
export type QuoteLine = Schemas["QuoteLineResource"];
/** The API documents `lines` loosely; they are QuoteLineResource items. */
export type RecoveredCart = Omit<Json<"/v1/checkout/recover/{token}">, "lines"> & { lines: QuoteLine[] };
export type CartItemInput = Schemas["QuoteCartRequest"]["items"][number];
export type PlaceOrderInput = Schemas["PlaceOrderRequest"];
export type SaveDraftInput = Schemas["SaveDraftRequest"];
export type PlacedOrder = paths["/v1/orders"]["post"]["responses"][201]["content"]["application/json"];
export type Order = Schemas["OrderResource"];
export type OrderStatus = Schemas["OrderStatus"];
export type TrackOrderInput = paths["/v1/orders/track"]["post"]["requestBody"]["content"]["application/json"];
export type Customer = Schemas["CustomerResource"];
export type CustomerRewards = Customer["rewards"];
export type PointPage = Json<"/v1/customer/points">;
export type CustomerAddress = Schemas["CustomerAddressResource"];
export type CustomerAddressInput = Schemas["CustomerAddressRequest"];
export type CustomerOrder = Schemas["CustomerOrderResource"];
export type CustomerOrderPage = Json<"/v1/customer/orders">;
export type OtpSent = paths["/v1/customer/otp"]["post"]["responses"][200]["content"]["application/json"];
export type Review = Schemas["ReviewResource"];
export type ReviewPage = Json<"/v1/products/{slug}/reviews">;
export type ProductQuestion = Schemas["ProductQuestionResource"];
export type QuestionPage = Json<"/v1/products/{slug}/questions">;
export type TrackedOrder = paths["/v1/orders/track"]["post"]["responses"][200]["content"]["application/json"];
