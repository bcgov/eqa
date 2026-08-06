Application::class, 'application_id');
    }

    /**
     * Get the education type associated with this offering.
     */
    public function educationType(): BelongsTo
    {
        return $this->belongsTo(EducationType::class, 'education_type_id');
    }
}