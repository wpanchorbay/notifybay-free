/* eslint-disable */
import React from "react";

export interface ClassicTableColumn {
  key: string;
  label: React.ReactNode | string;
  className?: string;
}

interface ClassicTableProps<T> {
  columns: ClassicTableColumn[];
  data: T[];
  renderCell: (item: T, columnKey: string) => React.ReactNode;
  keyField: keyof T;
  className?: string;
  expandedRowIds?: (string | number)[];
  expandedRowRender?: (item: T) => React.ReactNode;
}

/**
 * WordPress admin list table using `wp-list-table`, `widefat`, `striped`.
 * @param root0
 * @param root0.columns
 * @param root0.data
 * @param root0.renderCell
 * @param root0.keyField
 * @param root0.className
 */
export function ClassicTable<T>({
  columns,
  data,
  renderCell,
  keyField,
  className = "",
  expandedRowIds = [],
  expandedRowRender,
}: ClassicTableProps<T>) {
  return (
    <table className={`wp-list-table widefat fixed striped ${className}`}>
      <thead>
        <tr>
          {columns.map((col) => (
            <th key={col.key} id={col.key} className={col.className || ""}>
              {col.label}
            </th>
          ))}
        </tr>
      </thead>
      <tbody id="the-list">
        {data.length === 0 ? (
          <tr>
            <td colSpan={columns.length}>No items found.</td>
          </tr>
        ) : (
          data.map((item) => {
            const rowId = item[keyField] as unknown as string | number;
            const isExpanded = expandedRowIds.includes(rowId);
            return (
              <React.Fragment key={String(rowId)}>
                <tr className={isExpanded ? "is-expanded" : ""}>
                  {columns.map((col) => (
                    <td key={col.key} className={col.className || ""}>
                      {renderCell(item, col.key)}
                    </td>
                  ))}
                </tr>
                {isExpanded && expandedRowRender && expandedRowRender(item)}
              </React.Fragment>
            );
          })
        )}
      </tbody>
    </table>
  );
}
