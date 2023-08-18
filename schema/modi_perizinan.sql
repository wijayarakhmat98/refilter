--
-- PostgreSQL database dump
--

-- Dumped from database version 15.3
-- Dumped by pg_dump version 15.3

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: modi_perizinan; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.modi_perizinan (
    source integer NOT NULL,
    id integer NOT NULL,
    instance integer NOT NULL,
    jenis_perizinan text,
    nomor_perizinan text,
    tahapan_kegiatan text,
    wiup text,
    komoditas text,
    luas double precision,
    berlaku_awal date,
    berlaku_akhir date,
    tahapan_cnc text,
    lokasi text
);


--
-- Name: modi_perizinan modi_perizinan_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.modi_perizinan
    ADD CONSTRAINT modi_perizinan_pkey PRIMARY KEY (id, instance);


--
-- Name: modi_perizinan modi_perizinan_source_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.modi_perizinan
    ADD CONSTRAINT modi_perizinan_source_fkey FOREIGN KEY (source) REFERENCES public.raw(auto_id);


--
-- PostgreSQL database dump complete
--

