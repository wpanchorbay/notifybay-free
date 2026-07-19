import { FC, ReactNode, useState, useEffect } from "react";
import { __ } from "@wordpress/i18n";
import { applyFilters } from "@wordpress/hooks";
import apiFetch from "../utils/apiFetch";
import {
  ClassicTable,
  ClassicTableColumn,
} from "../components/classics/ClassicTable";
import { ClassicButton } from "../components/classics/ClassicButton";
import { useWpabStore } from "../store/wpabStore";
import CustomModal from "../components/common/CustomModal";
import { ClassicInput, ClassicSelect, ClassicSettingsTable, ClassicCheckbox } from "../components/classics";
import { useToast } from "../store/toast/use-toast";
import { LeadData } from "../utils/types";

interface PaginationResponse {
  data: LeadData[];
  total: number;
  total_pages: number;
  current_page: number;
}

const Leads: FC = () => {
  const store = useWpabStore();
  const { addToast } = useToast();
  const [leads, setLeads] = useState<LeadData[]>([]);
  const [loading, setLoading] = useState(true);
  const [currentPage, setCurrentPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [totalItems, setTotalItems] = useState(0);
  const [searchTerm, setSearchTerm] = useState("");
  const [selectedIds, setSelectedIds] = useState<number[]>([]);
  const [bulkAction, setBulkAction] = useState("");
  const itemsPerPage = 20;

  // Edit State
  const [editingLead, setEditingLead] = useState<LeadData | null>(null);
  const [isUpdating, setIsUpdating] = useState(false);

  const fetchLeads = (page: number, search: string = "") => {
    setLoading(true);
    apiFetch<PaginationResponse>({
      path: `admin/leads?page=${page}&per_page=${itemsPerPage}&search=${search}`,
    })
      .then((response) => {
        setLeads(response.data);
        setTotalPages(response.total_pages);
        setTotalItems(response.total);
        setLoading(false);
      })
      .catch(() => setLoading(false));
  };

  useEffect(() => {
    fetchLeads(currentPage, searchTerm);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [currentPage]);

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    setCurrentPage(1);
    fetchLeads(1, searchTerm);
  };

  const handleDelete = (id: number) => {
    if (
      // eslint-disable-next-line no-alert
      !confirm(__("Are you sure you want to delete this lead?", "notifybay"))
    ) {
      return;
    }

    apiFetch({
      path: `admin/leads/${id}`,
      method: "DELETE",
    }).then(() => {
      fetchLeads(currentPage, searchTerm);
      addToast(__("Lead deleted successfully.", "notifybay"), "success");
    });
  };

  const handleBulkAction = async () => {
    if (!bulkAction || selectedIds.length === 0) {
      return;
    }

    // Extension seam: let add-ons (e.g. NotifyBay Pro's "Resend failed") claim
    // bulk actions Free doesn't know. A handler returns a truthy value (a
    // success message string, or true) once it has processed the selection;
    // Free then just refreshes. Absent Pro, the filter returns the initial
    // `false` and Free runs its own built-in actions below.
    const handled = await applyFilters(
      "notifybay_lead_bulk_action",
      false,
      bulkAction,
      selectedIds,
    );
    if (handled) {
      fetchLeads(currentPage, searchTerm);
      setSelectedIds([]);
      setBulkAction("");
      addToast(
        typeof handled === "string"
          ? handled
          : __("Bulk action applied successfully.", "notifybay"),
        "success",
      );
      return;
    }

    if (
      bulkAction === "delete" &&
      !confirm(
        __("Are you sure you want to delete the selected leads?", "notifybay"),
      )
    ) {
      return;
    }

    apiFetch({
      path: "admin/leads/bulk",
      method: "POST",
      data: {
        ids: selectedIds,
        bulk_action: bulkAction,
      },
    }).then(() => {
      fetchLeads(currentPage, searchTerm);
      setSelectedIds([]);
      setBulkAction("");
      addToast(__("Bulk action applied successfully.", "notifybay"), "success");
    });
  };

  const handleExport = () => {
    const url = `${
      (window as any).notifybay.rest_url
    }notifybay/v1/admin/leads/export?_wpnonce=${
      (window as any).notifybay.nonce
    }`;
    window.open(url, "_blank");
  };

  const handleEdit = (lead: LeadData) => {
    setEditingLead({ ...lead });
  };

  const handleUpdateLead = () => {
    if (!editingLead) {
      return;
    }
    setIsUpdating(true);

    apiFetch({
      path: `admin/leads/${editingLead.id}`,
      method: "PUT",
      data: {
        user_email: editingLead.user_email,
        status: editingLead.status,
        target_price: editingLead.target_price,
      },
    })
      .then(() => {
        addToast(__("Lead updated successfully.", "notifybay"), "success");
        setEditingLead(null);
        fetchLeads(currentPage, searchTerm);
      })
      .finally(() => setIsUpdating(false));
  };

  const columns: ClassicTableColumn[] = [
    {
      key: "checkbox",
      className: "manage-column column-cb check-column",
      label: (
        <ClassicCheckbox
          labelClassName="notifybay-pl-[6px]"
          checked={selectedIds.length === leads.length && leads.length > 0}
          onChange={(checked) => {
            if (checked) {
              setSelectedIds(leads.map((l) => l.id));
            } else {
              setSelectedIds([]);
            }
          }}
        />
      ),
    },
    { key: "user_email", className: "column-primary", label: __("Email", "notifybay") },
    { key: "product_name", label: __("Product", "notifybay") },
    { key: "type", label: __("Type", "notifybay") },
    { key: "target_price", label: __("Target Price", "notifybay") },
    { key: "status", label: __("Status", "notifybay") },
    { key: "created_at", label: __("Date", "notifybay") },
  ];

  return (
    <div className="wrap notifybay-animate-fade-in">
      {/* Extension seam: add-ons (Pro) inject a banner here — e.g. the license
          activation CTA. Rendered inside #notifybay, so it survives the CSS that
          hides all native .notice elements on this page. Empty without Pro. */}
      {
        applyFilters(
          "notifybay_leads_banner",
          null,
        ) as ReactNode
      }

      <div className="notifybay-flex notifybay-w-full notifybay-justify-between notifybay-items-center" style={{ alignItems: 'center' }}>
        <h1 className="wp-heading-inline">{__("Lead Management", "notifybay")}</h1>
        <div className="notifybay-flex notifybay-items-center notifybay-gap-[12px]" style={{ alignItems: 'center' }}>
          <form onSubmit={handleSearch} className="search-box notifybay-flex notifybay-items-center notifybay-gap-[8px] notifybay-m-0" style={{ alignItems: 'center', margin: 0 }}>
            <label className="screen-reader-text" htmlFor="lead-search-input">
              {__("Search Leads:", "notifybay")}
            </label>
            <input
              type="search"
              id="lead-search-input"
              name="s"
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
              placeholder={__("Search...", "notifybay")}
              style={{ minHeight: '30px', margin: 0 }}
            />
            <input
              type="submit"
              id="search-submit"
              className="button"
              value={__("Search", "notifybay")}
              style={{ minHeight: '30px', margin: 0, padding: '0 10px', display: 'flex', alignItems: 'center' }}
            />
          </form>
          <button onClick={handleExport} className="page-title-action" style={{ margin: 0, top: 0, minHeight: '30px', display: 'flex', alignItems: 'center', padding: '0 10px' }}>
            {__("Export CSV ⬇", "notifybay")}
          </button>
        </div>
      </div>
      <hr className="wp-header-end" />

      <div id="leads-filter">

        <div className="tablenav top">
          <div className="alignleft actions bulkactions">
            <label htmlFor="bulk-action-selector-top" className="screen-reader-text">
              {__("Select bulk action", "notifybay")}
            </label>
            <select
              name="action"
              id="bulk-action-selector-top"
              value={bulkAction}
              onChange={(e) => setBulkAction(e.target.value)}
            >
              <option value="">{__("Bulk Actions", "notifybay")}</option>
              <option value="active">{__("Mark Active", "notifybay")}</option>
              <option value="expired">{__("Mark Expired", "notifybay")}</option>
              <option value="delete">{__("Delete", "notifybay")}</option>
              {(
                applyFilters("notifybay_lead_bulk_actions", []) as {
                  value: string;
                  label: string;
                }[]
              ).map((opt) => (
                <option key={opt.value} value={opt.value}>
                  {opt.label}
                </option>
              ))}
            </select>
            <button
              type="button"
              className="button action"
              onClick={handleBulkAction}
              disabled={!bulkAction || selectedIds.length === 0}
            >
              {__("Apply", "notifybay")}
            </button>
          </div>

          <div className="tablenav-pages">
            <span className="displaying-num">
              {totalItems} {__("items", "notifybay")}
            </span>
            <span className="pagination-links">
              {currentPage > 1 ? (
                <button
                  type="button"
                  className="first-page button"
                  onClick={() => setCurrentPage(1)}
                  disabled={loading}
                >
                  <span aria-hidden="true">&laquo;</span>
                </button>
              ) : (
                <span className="tablenav-pages-navspan button disabled" aria-hidden="true">&laquo;</span>
              )}
              {currentPage > 1 ? (
                <button
                  type="button"
                  className="prev-page button"
                  onClick={() => setCurrentPage((p) => Math.max(1, p - 1))}
                  disabled={loading}
                >
                  <span aria-hidden="true">&lsaquo;</span>
                </button>
              ) : (
                <span className="tablenav-pages-navspan button disabled" aria-hidden="true">&lsaquo;</span>
              )}
              <span className="paging-input">
                <span className="tablenav-paging-text">
                  {currentPage} {__("of", "notifybay")}{" "}
                  <span className="total-pages">{totalPages}</span>
                </span>
              </span>
              {currentPage < totalPages ? (
                <button
                  type="button"
                  className="next-page button"
                  onClick={() => setCurrentPage((p) => Math.min(totalPages, p + 1))}
                  disabled={loading}
                >
                  <span aria-hidden="true">&rsaquo;</span>
                </button>
              ) : (
                <span className="tablenav-pages-navspan button disabled" aria-hidden="true">&rsaquo;</span>
              )}
              {currentPage < totalPages ? (
                <button
                  type="button"
                  className="last-page button"
                  onClick={() => setCurrentPage(totalPages)}
                  disabled={loading}
                >
                  <span aria-hidden="true">&raquo;</span>
                </button>
              ) : (
                <span className="tablenav-pages-navspan button disabled" aria-hidden="true">&raquo;</span>
              )}
            </span>
          </div>
          <br className="clear" />
        </div>

        <ClassicTable
          columns={columns}
          data={leads}
          keyField="id"
          expandedRowIds={editingLead ? [editingLead.id] : []}
          expandedRowRender={() => {
            if (!editingLead) return null;
            return (
              <tr className="inline-edit-row">
                <td colSpan={columns.length} className="colspanchange">
                  <div className="notifybay-p-[24px]">
                    <h3 className="notifybay-text-[16px] notifybay-font-[600] notifybay-text-gray-900 notifybay-mb-[16px]">
                      {__("Quick Edit", "notifybay")}
                    </h3>
                    <ClassicSettingsTable
                      fields={[
                        {
                          id: "email",
                          label: __("Email Address", "notifybay"),
                          render: () => (
                            <ClassicInput
                              type="email"
                              value={editingLead.user_email}
                              onChange={(e: any) =>
                                setEditingLead({ ...editingLead, user_email: e.target.value })
                              }
                            />
                          ),
                        },
                        {
                          id: "status",
                          label: __("Status", "notifybay"),
                          render: () => (
                            <ClassicSelect
                              value={editingLead.status}
                              onChange={(val: string) =>
                                setEditingLead({ ...editingLead, status: val })
                              }
                              options={[
                                { value: "active", label: __("Active", "notifybay") },
                                { value: "pending_verification", label: __("Pending Verification", "notifybay") },
                                { value: "notified", label: __("Notified", "notifybay") },
                                { value: "converted", label: __("Converted", "notifybay") },
                                { value: "expired", label: __("Expired", "notifybay") },
                                { value: "failed", label: __("Failed", "notifybay") },
                                { value: "unsubscribed", label: __("Unsubscribed", "notifybay") },
                              ]}
                            />
                          ),
                        },
                        ...(editingLead.price_at_subscription !== null
                          ? [
                              {
                                id: "target_price",
                                label: __("Target Price", "notifybay"),
                                render: () => (
                                  <ClassicInput
                                    type="number"
                                    value={(editingLead.price_at_subscription || "").toString()}
                                    onChange={(e: any) =>
                                      setEditingLead({
                                        ...editingLead,
                                        price_at_subscription: e.target.value ? parseFloat(e.target.value) : null,
                                      })
                                    }
                                  />
                                ),
                              },
                            ]
                          : []),
                      ]}
                    />
                    <div className="notifybay-flex notifybay-gap-[12px] notifybay-mt-[24px]">
                      <ClassicButton variant="secondary" onClick={() => setEditingLead(null)}>
                        {__("Cancel", "notifybay")}
                      </ClassicButton>
                      <ClassicButton variant="primary" onClick={handleUpdateLead} disabled={isUpdating}>
                        {isUpdating ? __("Saving…", "notifybay") : __("Update", "notifybay")}
                      </ClassicButton>
                    </div>
                  </div>
                </td>
              </tr>
            );
          }}
          renderCell={(item, columnKey) => {
            if (columnKey === "checkbox") {
              return (
                <ClassicCheckbox
                  labelClassName="notifybay-pl-[6px]"
                  checked={selectedIds.includes(item.id)}
                  onChange={(checked) => {
                    if (checked) {
                      setSelectedIds([...selectedIds, item.id]);
                    } else {
                      setSelectedIds(
                        selectedIds.filter((id) => id !== item.id),
                      );
                    }
                  }}
                />
              );
            }
            if (columnKey === "user_email") {
              return (
                <>
                  <strong>
                    <a href="#" onClick={(e) => { e.preventDefault(); handleEdit(item); }} className="row-title">
                      {item.user_email}
                    </a>
                  </strong>
                  <div className="row-actions">
                    <span className="edit">
                      <button
                        type="button"
                        className="button-link"
                        onClick={() => handleEdit(item)}
                      >
                        {__("Edit", "notifybay")}
                      </button> |{" "}
                    </span>
                    <span className="delete">
                      <button
                        type="button"
                        className="button-link submitdelete notifybay-text-red-500"
                        onClick={() => handleDelete(item.id)}
                      >
                        {__("Delete", "notifybay")}
                      </button>
                    </span>
                    {applyFilters("notifybay_lead_row_actions", null, item, {
                      refresh: () => fetchLeads(currentPage, searchTerm),
                    }) as ReactNode}
                  </div>
                  <button type="button" className="toggle-row">
                    <span className="screen-reader-text">{__("Show more details", "notifybay")}</span>
                  </button>
                </>
              );
            }
            if (columnKey === "status") {
              const statusColors: any = {
                active: "notifybay-bg-green-100 notifybay-text-green-700",
                notified: "notifybay-bg-blue-100 notifybay-text-blue-700",
                converted: "notifybay-bg-purple-100 notifybay-text-purple-700",
                expired: "notifybay-bg-gray-100 notifybay-text-gray-700",
                failed: "notifybay-bg-red-100 notifybay-text-red-700",
              };
              return (
                <span
                  className={`notifybay-px-2 notifybay-py-1 notifybay-rounded-full notifybay-text-[11px] notifybay-font-bold notifybay-uppercase notifybay-tracking-tight ${
                    statusColors[item.status] || ""
                  }`}
                >
                  {item.status}
                </span>
              );
            }
            if (columnKey === "type") {
              return (
                <span className="notifybay-text-gray-600 notifybay-text-[13px] notifybay-font-medium">
                  {item.type === "waitlist" ? "⏳ Waitlist" : "⭐ Wishlist"}
                </span>
              );
            }
            if (columnKey === "target_price") {
              if (item.type === "waitlist" || !item.target_price) {
                return <span className="notifybay-text-gray-300">-</span>;
              }
              return (
                <span className="notifybay-text-[14px] notifybay-font-medium">
                  {new Intl.NumberFormat("en-US", {
                    style: "currency",
                    currency: item.user_currency || store.currency.code,
                  }).format(item.target_price)}
                </span>
              );
            }
            return (
              <span className="notifybay-text-[14px]">
                {item[columnKey as keyof LeadData]}
              </span>
            );
          }}
        />

        <div className="tablenav bottom">
          <div className="tablenav-pages">
            <span className="displaying-num">
              {totalItems} {__("items", "notifybay")}
            </span>
            <span className="pagination-links">
              {currentPage > 1 ? (
                <button
                  type="button"
                  className="first-page button"
                  onClick={() => setCurrentPage(1)}
                  disabled={loading}
                >
                  <span aria-hidden="true">&laquo;</span>
                </button>
              ) : (
                <span className="tablenav-pages-navspan button disabled" aria-hidden="true">&laquo;</span>
              )}
              {currentPage > 1 ? (
                <button
                  type="button"
                  className="prev-page button"
                  onClick={() => setCurrentPage((p) => Math.max(1, p - 1))}
                  disabled={loading}
                >
                  <span aria-hidden="true">&lsaquo;</span>
                </button>
              ) : (
                <span className="tablenav-pages-navspan button disabled" aria-hidden="true">&lsaquo;</span>
              )}
              <span className="paging-input">
                <span className="tablenav-paging-text">
                  {currentPage} {__("of", "notifybay")}{" "}
                  <span className="total-pages">{totalPages}</span>
                </span>
              </span>
              {currentPage < totalPages ? (
                <button
                  type="button"
                  className="next-page button"
                  onClick={() => setCurrentPage((p) => Math.min(totalPages, p + 1))}
                  disabled={loading}
                >
                  <span aria-hidden="true">&rsaquo;</span>
                </button>
              ) : (
                <span className="tablenav-pages-navspan button disabled" aria-hidden="true">&rsaquo;</span>
              )}
              {currentPage < totalPages ? (
                <button
                  type="button"
                  className="last-page button"
                  onClick={() => setCurrentPage(totalPages)}
                  disabled={loading}
                >
                  <span aria-hidden="true">&raquo;</span>
                </button>
              ) : (
                <span className="tablenav-pages-navspan button disabled" aria-hidden="true">&raquo;</span>
              )}
            </span>
          </div>
          <br className="clear" />
        </div>
      </div>
    </div>
  );
};

export default Leads;
