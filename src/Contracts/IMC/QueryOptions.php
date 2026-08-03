<?php

namespace SchoolPalm\ModuleBridge\Contracts\IMC;

class QueryOptions
{
    /**
     * ---------------------------------------
     * Free text search
     * ---------------------------------------
     */
    public ?string $search = null;

    /**
     * ---------------------------------------
     * Exact match filters
     * Example: ['status' => 'active']
     * ---------------------------------------
     */
    public array $filters = [];

    /**
     * ---------------------------------------
     * Fields to search within (optional)
     * Example: ['first_name', 'last_name']
     * ---------------------------------------
     */
    public array $searchFields = [];

    /**
     * ---------------------------------------
     * Sorting field
     * ---------------------------------------
     */
    public ?string $sortBy = null;

    /**
     * asc | desc
     */
    public string $direction = 'asc';

    /**
     * ---------------------------------------
     * Pagination
     * ---------------------------------------
     */
    public int $page = 1;

    public int $perPage = 10;

    /**
     * ---------------------------------------
     * Select specific fields (optimization)
     * ---------------------------------------
     */
    public array $select = [];

    /**
     * ---------------------------------------
     * Constructor helper (fluent)
     * ---------------------------------------
     */
    public function __construct(array $data = [])
    {
        $this->search       = $data['search'] ?? null;
        $this->filters      = $data['filters'] ?? [];
        $this->searchFields = $data['searchFields'] ?? [];
        $this->sortBy       = $data['sortBy'] ?? null;
        $this->direction    = $data['direction'] ?? 'asc';
        $this->page         = $data['page'] ?? 1;
        $this->perPage      = $data['perPage'] ?? 10;
        $this->select       = $data['select'] ?? [];
    }

    /**
     * ---------------------------------------
     * Fluent helpers
     * ---------------------------------------
     */
    public function where(string $field, mixed $value): self
    {
        $this->filters[$field] = $value;
        return $this;
    }

    public function search(string $term, array $fields = []): self
    {
        $this->search = $term;
        $this->searchFields = $fields;

        return $this;
    }

    public function orderBy(string $field, string $direction = 'asc'): self
    {
        $this->sortBy = $field;
        $this->direction = $direction;

        return $this;
    }

    public function paginate(int $page, int $perPage): self
    {
        $this->page = $page;
        $this->perPage = $perPage;

        return $this;
    }

    public function select(array $fields): self
    {
        $this->select = $fields;

        return $this;
    }

    /**
     * ---------------------------------------
     * Convert to array (for transport)
     * ---------------------------------------
     */
    public function toArray(): array
    {
        return [
            'search'       => $this->search,
            'filters'      => $this->filters,
            'searchFields' => $this->searchFields,
            'sortBy'       => $this->sortBy,
            'direction'    => $this->direction,
            'page'         => $this->page,
            'perPage'      => $this->perPage,
            'select'       => $this->select,
        ];
    }
}