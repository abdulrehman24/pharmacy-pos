export interface User {
    id: number;
    name: string;
    email: string;
    role: 'admin' | 'manager' | 'pharmacist' | 'cashier' | 'inventory' | 'accounts' | string;
    is_active: boolean;
    phone?: string;
    pin_code?: string;
    email_verified_at?: string;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
    };
    flash?: {
        success?: string;
        error?: string;
    };
};

export interface MedicinePackage {
    id: number;
    name: string;
    base_quantity: number;
    sell_price_paisa: number;
    default_sell_price_paisa?: number;
    default_purchase_price_paisa?: number;
    is_default_sale: boolean;
}

export interface MedicineItem {
    id: number;
    code: string;
    brand_name: string;
    generic_name?: string;
    strength?: string;
    dosage_form: string;
    base_unit: string;
    rack_shelf?: string;
    reorder_level?: number;
    prescription_required: boolean;
    sellable_stock_base: number;
    packages: MedicinePackage[];
    selected_package_id?: number;
}
