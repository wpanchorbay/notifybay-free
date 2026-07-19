import React, { useState } from 'react';
import {
	ClassicButton,
	ClassicInput,
	ClassicFormField,
	ClassicOptionsGroup,
	ClassicSelect,
	ClassicCheckbox,
	ClassicTooltip,
	ClassicRepeater,
	ClassicTable,
	ClassicSettingsTable,
	ClassicMultiSelect,
} from '../components/classics';

/* ───── helpers ───── */
const Section: React.FC< { title?: string; children: React.ReactNode } > = ( {
	title,
	children,
} ) => (
	<div className="notifybay-section notifybay-ignore-preflight">
		{ title && (
			<h2 className="title notifybay-ignore-preflight">{ title }</h2>
		) }
		{ children }
	</div>
);

/* ───── page ───── */
const ClassicShowcase: React.FC = () => {
	const [ checkboxA, setCheckboxA ] = useState( false );
	const [ checkboxB, setCheckboxB ] = useState( true );
	const [ selectVal, setSelectVal ] = useState< string | number >(
		'option-1'
	);
	const [ multiVal, setMultiVal ] = useState< ( string | number )[] >( [] );
	const [ inputVal, setInputVal ] = useState( '' );
	const [ repeaterItems, setRepeaterItems ] = useState( [
		{ id: '1', title: 'Add‑on Group 1' },
		{ id: '2', title: 'Add‑on Group 2' },
	] );

	const selectOptions = [
		{ value: 'option-1', label: 'Option 1' },
		{ value: 'option-2', label: 'Option 2' },
		{ value: 'option-3', label: 'Option 3' },
		{
			value: 'option-pro',
			label: 'Pro Feature',
			variant: 'buy_pro' as const,
		},
		{
			value: 'option-soon',
			label: 'Upcoming',
			variant: 'coming_soon' as const,
		},
	];

	const tableColumns = [
		{ key: 'id', label: 'ID' },
		{ key: 'name', label: 'Name' },
		{ key: 'status', label: 'Status' },
	];

	const tableData = [
		{ id: 1, name: 'Extra Toppings', status: 'Active' },
		{ id: 2, name: 'Gift Wrapping', status: 'Active' },
		{ id: 3, name: 'Monogram', status: 'Inactive' },
	];

	return (
		<div className=" notifybay-space-y-[32px]">
			<div className="notifybay-grid notifybay-grid-cols-1 notifybay-gap-[32px]">
				{ /* ── Buttons ── */ }
				<Section title="1. Buttons &amp; Actions">
					<p>
						<ClassicButton variant="primary">
							Primary Button
						</ClassicButton>{ ' ' }
						<ClassicButton variant="secondary">
							Secondary Button
						</ClassicButton>{ ' ' }
						<ClassicButton variant="link">
							Link Button
						</ClassicButton>{ ' ' }
						<ClassicButton variant="link-delete">
							Delete Link
						</ClassicButton>{ ' ' }
						<ClassicButton variant="action">
							WC Action
						</ClassicButton>
					</p>
					<p>
						<ClassicButton variant="primary" disabled>
							Disabled Primary
						</ClassicButton>{ ' ' }
						<ClassicButton variant="secondary" disabled>
							Disabled Secondary
						</ClassicButton>
					</p>
				</Section>

				{ /* ── Inputs ── */ }
				<Section title="2. Form Inputs &amp; Sizing">
					<table className="form-table">
						<tbody>
							<tr>
								<th scope="row">Regular Text (25em)</th>
								<td>
									<ClassicInput
										size="regular"
										placeholder="regular-text"
										value={ inputVal }
										onChange={ ( e ) =>
											setInputVal( e.target.value )
										}
									/>
								</td>
							</tr>
							<tr>
								<th scope="row">Short (WC ~50%)</th>
								<td>
									<ClassicInput
										size="short"
										placeholder="short"
										description="Used for prices, dimensions etc."
									/>
								</td>
							</tr>
							<tr>
								<th scope="row">Small Text (50px)</th>
								<td>
									<ClassicInput
										size="small"
										type="number"
										placeholder="0"
										description="Good for quantities."
									/>
								</td>
							</tr>
							<tr>
								<th scope="row">Large Text (100%)</th>
								<td>
									<ClassicInput
										size="large"
										placeholder="large-text"
									/>
								</td>
							</tr>
							<tr>
								<th scope="row">WC Price Input</th>
								<td>
									<ClassicInput
										size="short"
										inputType="price"
										placeholder="0.00"
										description="wc_input_price class applied."
									/>
								</td>
							</tr>
							<tr>
								<th scope="row">WC Decimal Input</th>
								<td>
									<ClassicInput
										size="short"
										inputType="decimal"
										placeholder="0.00"
										description="wc_input_decimal class applied."
									/>
								</td>
							</tr>
						</tbody>
					</table>
				</Section>

				{ /* ── WooCommerce Form Layout ── */ }
				<Section title="3. WooCommerce Form Layouts">
					<ClassicOptionsGroup>
						<ClassicFormField label="Option Name">
							<input
								type="text"
								className="short"
								placeholder="e.g. Color"
							/>
						</ClassicFormField>
						<ClassicFormField
							label="Option Price"
							description="The additional cost for this option."
						>
							<input
								type="text"
								className="short wc_input_price"
								placeholder="0.00"
							/>
							<ClassicTooltip tip="This price will be added to the product total." />
						</ClassicFormField>
					</ClassicOptionsGroup>

					<ClassicOptionsGroup>
						<ClassicFormField
							label="Half Width (First)"
							layout="first"
						>
							<input
								type="text"
								className="short"
								placeholder="First"
							/>
						</ClassicFormField>
						<ClassicFormField
							label="Half Width (Last)"
							layout="last"
						>
							<input
								type="text"
								className="short"
								placeholder="Last"
							/>
						</ClassicFormField>
					</ClassicOptionsGroup>
				</Section>

				{ /* ── Select ── */ }
				<Section title="4. Select Dropdowns">
					<table className="form-table">
						<tbody>
							<tr>
								<th scope="row">Short Select</th>
								<td>
									<ClassicSelect
										size="short"
										options={ selectOptions }
										value={ selectVal }
										onChange={ ( val ) =>
											setSelectVal( val )
										}
										description="WC short-width select."
									/>
								</td>
							</tr>
							<tr>
								<th scope="row">Regular Select</th>
								<td>
									<ClassicSelect
										size="regular"
										options={ selectOptions }
										value={ selectVal }
										onChange={ ( val ) =>
											setSelectVal( val )
										}
									/>
								</td>
							</tr>
							<tr>
								<th scope="row">Custom Render</th>
								<td>
									<ClassicSelect
										size="regular"
										options={ [
											{ value: 'red', label: 'Red' },
											{ value: 'green', label: 'Green' },
											{ value: 'blue', label: 'Blue' },
										] }
										value={ selectVal }
										onChange={ ( val ) =>
											setSelectVal( val )
										}
										renderOption={ ( opt ) => (
											<>
												<span
													style={ {
														display: 'inline-block',
														width: 10,
														height: 10,
														backgroundColor:
															opt.value as string,
														marginRight: 8,
														borderRadius: '50%',
													} }
												/>
												{ opt.label }
											</>
										) }
										description="Custom rendering with color dots."
									/>
								</td>
							</tr>
							<tr>
								<th scope="row">Searchable Classic Select</th>
								<td>
									<ClassicSelect
										size="regular"
										options={ selectOptions }
										value={ selectVal }
										onChange={ ( val ) =>
											setSelectVal( val )
										}
										enableSearch={ true }
										description="Dropdown with search and variants."
									/>
								</td>
							</tr>
							<tr>
								<th scope="row">MultiSelect Custom</th>
								<td>
									<ClassicMultiSelect
										options={ [
											{ value: 'react', label: 'React' },
											{ value: 'vue', label: 'Vue' },
											{
												value: 'angular',
												label: 'Angular',
											},
											{
												value: 'svelte',
												label: 'Svelte Pro',
												variant: 'buy_pro',
											},
											{
												value: 'solid',
												label: 'Solid',
												variant: 'coming_soon',
											},
										] }
										value={ multiVal }
										onChange={ ( val ) =>
											setMultiVal( val )
										}
										renderOption={ ( opt ) => (
											<span
												style={ {
													fontWeight: 'bold',
													color: '#3858e9',
												} }
											>
												🚀 { opt.label }
											</span>
										) }
										placeholder="Choose frameworks..."
									/>
								</td>
							</tr>
						</tbody>
					</table>
				</Section>

				{ /* ── Checkboxes ── */ }
				<Section title="5. Checkboxes">
					<table className="form-table">
						<tbody>
							<tr>
								<th scope="row">Enable Feature</th>
								<td>
									<ClassicCheckbox
										label="Turn this feature on"
										checked={ checkboxA }
										onChange={ setCheckboxA }
										description="This enables the main feature."
									/>
								</td>
							</tr>
							<tr>
								<th scope="row">Delete on Uninstall</th>
								<td>
									<ClassicCheckbox
										label="Remove all data when plugin is deleted"
										checked={ checkboxB }
										onChange={ setCheckboxB }
									/>
								</td>
							</tr>
						</tbody>
					</table>
				</Section>

				{ /* ── Tooltips ── */ }
				<Section title="6. Tooltips &amp; Icons">
					<p>
						Hover the icon to see the WooCommerce-native tooltip:{ ' ' }
						<ClassicTooltip tip="This is a WooCommerce-style help tooltip using the woocommerce-help-tip class." />
					</p>
					<p>
						Dashicons:{ ' ' }
						<span
							className="dashicons dashicons-menu"
							title="Drag handle"
						/>{ ' ' }
						<span
							className="dashicons dashicons-trash"
							title="Delete"
						/>{ ' ' }
						<span
							className="dashicons dashicons-edit"
							title="Edit"
						/>{ ' ' }
						<span
							className="dashicons dashicons-visibility"
							title="View"
						/>
					</p>
				</Section>

				{ /* ── Repeater ── */ }
				<Section title="7. WooCommerce Repeater UI">
					<ClassicRepeater
						items={ repeaterItems }
						onAdd={ () =>
							setRepeaterItems( ( prev ) => [
								...prev,
								{
									id: String( Date.now() ),
									title: `Add‑on Group ${ prev.length + 1 }`,
								},
							] )
						}
						onRemove={ ( id ) =>
							setRepeaterItems( ( prev ) =>
								prev.filter( ( i ) => i.id !== id )
							)
						}
						addLabel="Add Add‑on Group"
						renderItem={ ( item ) => (
							<table
								className="form-table"
								style={ { margin: 0 } }
							>
								<tbody>
									<tr>
										<th scope="row">Name</th>
										<td>
											<input
												type="text"
												className="regular-text"
												defaultValue={ item.title }
											/>
										</td>
									</tr>
									<tr>
										<th scope="row">Type</th>
										<td>
											<ClassicSelect
												size="short"
												options={ [
													{
														value: 'checkbox',
														label: 'Checkbox',
													},
													{
														value: 'radio',
														label: 'Radio Button',
													},
													{
														value: 'select',
														label: 'Dropdown',
													},
													{
														value: 'text',
														label: 'Text Input',
													},
												] }
												value="select"
												onChange={ () => {} }
											/>
										</td>
									</tr>
								</tbody>
							</table>
						) }
					/>
				</Section>

				{ /* ── Table ── */ }
				<Section title="8. WP List Table">
					<ClassicTable
						columns={ tableColumns }
						data={ tableData }
						keyField="id"
						renderCell={ ( item: any, key: any ) => {
							if ( key === 'status' ) {
								return (
									<span
										style={ {
											color:
												item.status === 'Active'
													? 'green'
													: '#999',
										} }
									>
										{ item.status }
									</span>
								);
							}
							return String( item[ key as keyof typeof item ] );
						} }
					/>
				</Section>

				{ /* ── Settings Table ── */ }
				<Section title="9. WooCommerce Settings Layouts (form-table)">
					<ClassicSettingsTable
						title="Store Address"
						description="This is where your business is located. Tax rates and shipping rates will use this address."
						fields={ [
							{
								id: 'address_1',
								label: 'Address line 1',
								tooltip:
									'The street address for your business location.',
								render: () => (
									<ClassicInput
										size="regular"
										placeholder="Dhaka"
									/>
								),
							},
							{
								id: 'address_2',
								label: 'Address line 2',
								tooltip:
									'Apartment, suite, unit etc. (optional)',
								render: () => <ClassicInput size="regular" />,
							},
							{
								id: 'city',
								label: 'City',
								tooltip: 'City, town or village.',
								render: () => (
									<ClassicInput
										size="regular"
										placeholder="Dhaka"
									/>
								),
							},
							{
								id: 'country_state',
								label: 'Country / State',
								tooltip:
									'Choose the country and state for your store.',
								render: () => (
									<ClassicSelect
										size="regular"
										options={ [
											{
												value: 'bd-syl',
												label: 'Bangladesh — Sylhet',
											},
										] }
										value="bd-syl"
										onChange={ () => {} }
									/>
								),
							},
							{
								id: 'postcode',
								label: 'Postcode / ZIP',
								tooltip: 'Postal code for your business.',
								render: () => (
									<ClassicInput
										size="regular"
										placeholder="1000"
									/>
								),
							},
						] }
					/>

					<ClassicSettingsTable
						title="General options"
						fields={ [
							{
								label: 'Selling location(s)',
								tooltip:
									'Choose which countries you want to sell to.',
								render: () => (
									<ClassicSelect
										size="regular"
										options={ [
											{
												value: 'all',
												label: 'Sell to all countries',
											},
										] }
										value="all"
										onChange={ () => {} }
									/>
								),
							},
							{
								label: 'Shipping location(s)',
								tooltip:
									'Choose which countries you want to ship to.',
								render: () => (
									<ClassicSelect
										size="regular"
										options={ [
											{
												value: 'all',
												label: 'Ship to all countries you sell to',
											},
										] }
										value="all"
										onChange={ () => {} }
									/>
								),
							},
							{
								label: 'Default customer location',
								tooltip:
									"Determines the customer's default location.",
								render: () => (
									<ClassicSelect
										size="regular"
										options={ [
											{
												value: 'shop',
												label: 'Shop country/region',
											},
										] }
										value="shop"
										onChange={ () => {} }
									/>
								),
							},
						] }
					/>

					<ClassicSettingsTable
						title="Taxes and coupons"
						description="Enable taxes and coupons and configure how they are calculated."
						fields={ [
							{
								label: 'Enable taxes',
								render: () => (
									<ClassicCheckbox
										label="Enable tax rates and calculations"
										description="Rates will be configurable and taxes will be calculated during checkout."
										checked={ checkboxA }
										onChange={ setCheckboxA }
									/>
								),
							},
							{
								label: 'Enable coupons',
								render: () => (
									<div className="notifybay-flex notifybay-flex-col notifybay-gap-2">
										<ClassicCheckbox
											label="Enable the use of coupon codes"
											description="Coupons can be applied from the cart and checkout pages."
											checked={ checkboxB }
											onChange={ setCheckboxB }
										/>
										<ClassicCheckbox
											label="Calculate coupon discounts sequentially"
											description="When applying multiple coupons, apply the first coupon to the full price and the second coupon to the discounted price and so on."
											checked={ false }
											onChange={ () => {} }
										/>
									</div>
								),
							},
						] }
					/>

					<ClassicSettingsTable
						title="Currency options"
						description="The following options affect how prices are displayed on the frontend."
						fields={ [
							{
								label: 'Currency',
								tooltip:
									'Choose the default currency for the store.',
								render: () => (
									<ClassicSelect
										size="regular"
										options={ [
											{
												value: 'usd',
												label: 'United States (US) dollar ($) — USD',
											},
										] }
										value="usd"
										onChange={ () => {} }
									/>
								),
							},
							{
								label: 'Currency position',
								tooltip:
									'Choose the location of the currency symbol.',
								render: () => (
									<ClassicSelect
										size="regular"
										options={ [
											{ value: 'right', label: 'Right' },
										] }
										value="right"
										onChange={ () => {} }
									/>
								),
							},
							{
								label: 'Thousand separator',
								tooltip:
									'Character used for thousand separation.',
								render: () => (
									<ClassicInput
										size="small"
										placeholder=","
									/>
								),
							},
							{
								label: 'Decimal separator',
								tooltip:
									'Character used for decimal separation.',
								render: () => (
									<ClassicInput
										size="small"
										placeholder="."
									/>
								),
							},
						] }
					/>
					<p className="submit">
						<ClassicButton variant="primary">
							Save Changes
						</ClassicButton>
					</p>
				</Section>
			</div>
		</div>
	);
};

export default ClassicShowcase;
