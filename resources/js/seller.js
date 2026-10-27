/*
 * Supplier panel entry: registers the catalog tools' Alpine components, then loads the
 * shared storefront bundle (which starts Alpine). Imports run in order, so the
 * "alpine:init" listener below is in place before app.js calls Alpine.start().
 */
import './seller/register';
import './app';
