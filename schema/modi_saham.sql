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
-- Name: modi_saham; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.modi_saham (
    source integer NOT NULL,
    id integer NOT NULL,
    instance integer NOT NULL,
    jenis_saham text,
    pemegang_saham text,
    negara text,
    persentase double precision,
    keterangan text
);


--
-- Name: modi_saham modi_saham_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.modi_saham
    ADD CONSTRAINT modi_saham_pkey PRIMARY KEY (id, instance);


--
-- Name: modi_saham modi_saham_source_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.modi_saham
    ADD CONSTRAINT modi_saham_source_fkey FOREIGN KEY (source) REFERENCES public.raw(auto_id);


--
-- PostgreSQL database dump complete
--

